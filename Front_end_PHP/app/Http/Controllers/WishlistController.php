<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\WishlistProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WishlistController
 *
 * Handles all wishlist-related functionality including:
 * - Displaying wishlist contents
 * - Adding/removing/updating items with quantities
 * - Transferring session wishlist to database after login
 */
class WishlistController extends Controller
{
    /**
     * Display the wishlist page with all items
     *
     * For authenticated users, retrieves wishlist from database
     * For guest users, retrieves wishlist from session
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $wishlistItems = [];
        $subtotal = 0;
        $totalQuantity = 0;

        if (session()->has('user_id')) {
            $userId = session('user_id');
            $wishlist = Wishlist::with('products')->where('user_id', $userId)->first();

            if ($wishlist) {
                foreach ($wishlist->products as $product) {
                    if (!$product) {
                        continue; // Skip if null
                    }

                    $quantity = $product->pivot->product_quantity ?? 0;
                    $price = $product->price_after_discount ?? $product->unit_price;
                    $wishlistItems[] = [
                        'id' => $product->product_id,
                        'name' => $product->product_name,
                        'price' => $price,
                        'quantity' => $quantity,
                        'item_total' => $price * $quantity,
                        'image' => route('trader.product.image', $product->product_id),
                        'stock' => $product->stock
                    ];
                    $subtotal += $price * $quantity;
                    $totalQuantity += $quantity;
                }
            }
        } else {
            // Guest user - get wishlist from session
            $sessionWishlist = session('wishlist', []);

            foreach ($sessionWishlist as $productId => $item) {
                $product = Product::find($productId);
                if ($product) {
                    $quantity = $item['quantity'];
                    $price = $product->price_after_discount ?? $product->unit_price;
                    $wishlistItems[] = [
                        'id' => $product->product_id,
                        'name' => $product->product_name,
                        'price' => $price,
                        'quantity' => $quantity,
                        'item_total' => $price * $quantity,
                        'image' => route('trader.product.image', $product->product_id),
                        'stock' => $product->stock
                    ];
                    $subtotal += $price * $quantity;
                    $totalQuantity += $quantity;
                }
            }
        }

        return view('wishlist', [
            'items' => $wishlistItems,
            'subtotal' => $subtotal,
            'total' => $subtotal, // Discounts can be added later
            'total_quantity' => $totalQuantity,
            'remaining_items' => 20 - $totalQuantity,
            'isAuthenticated' => session()->has('user_id')
        ]);
    }

    /**
     * Add a product to the wishlist
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addToWishlist(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:PRODUCT,product_id',
            'quantity' => 'required|integer|min:1'
        ]);

        $product = Product::findOrFail($request->product_id);

        // Check stock availability
        if ($product->stock < $request->quantity) {
            return back()->with('error', 'Not enough stock available');
        }

        // Calculate current wishlist total quantity
        $currentWishlistQuantity = 0;

        if (session()->has('user_id')) {
            $userId = session('user_id');
            $wishlist = Wishlist::where('user_id', $userId)->first();

            if ($wishlist) {
                $currentWishlistQuantity = DB::table('WISHLIST_PRODUCT')
                    ->where('wishlist_id', $wishlist->wishlist_id)
                    ->sum('product_quantity');
            }
        } else {
            // Guest user - check session wishlist
            $sessionWishlist = session('wishlist', []);
            foreach ($sessionWishlist as $item) {
                $currentWishlistQuantity += $item['quantity'];
            }
        }

        // Check if adding this quantity would exceed the 20 item limit
        $newTotalQuantity = $currentWishlistQuantity + $request->quantity;
        if ($newTotalQuantity > 20) {
            $remainingSpace = 20 - $currentWishlistQuantity;
            if ($remainingSpace <= 0) {
                return back()->with('error', 'Your wishlist is full! Maximum 20 items allowed.');
            } else {
                return back()->with('error', "You can only add {$remainingSpace} more item(s) to your wishlist. Maximum 20 items allowed.");
            }
        }

        if (session()->has('user_id')) {
            $userId = session('user_id');

            // Get or create user's wishlist
            $wishlist = Wishlist::firstOrCreate(
                ['user_id' => $userId],
                [
                    'wishlist_id' => 'wishlist' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'creation_date' => now()
                ]
            );

            // Find existing wishlist product
            $wishlistProduct = WishlistProduct::where('wishlist_id', $wishlist->wishlist_id)
                ->where('product_id', $product->product_id)
                ->first();

            if ($wishlistProduct) {
                // Calculate new quantities
                $newQuantity = $wishlistProduct->product_quantity + $request->quantity;

                // Double-check quantity limit for existing products
                $otherProductsQuantity = DB::table('WISHLIST_PRODUCT')
                    ->where('wishlist_id', $wishlist->wishlist_id)
                    ->where('product_id', '!=', $product->product_id)
                    ->sum('product_quantity');

                if (($otherProductsQuantity + $newQuantity) > 20) {
                    $maxAllowed = 20 - $otherProductsQuantity;
                    return back()->with('error', "You can only add {$maxAllowed} more of this item. Maximum 20 items allowed.");
                }

                // Delete old row to avoid potential trigger issues
                $wishlistProduct->delete();

                // Insert new row with updated quantity
                WishlistProduct::create([
                    'wishlist_id' => $wishlist->wishlist_id,
                    'product_id' => $product->product_id,
                    'product_quantity' => $newQuantity
                ]);
            } else {
                // Insert new row
                WishlistProduct::create([
                    'wishlist_id' => $wishlist->wishlist_id,
                    'product_id' => $product->product_id,
                    'product_quantity' => $request->quantity
                ]);
            }
        } else {
            // Guest user - store in session
            $wishlist = session('wishlist', []);

            if (isset($wishlist[$product->product_id])) {
                $wishlist[$product->product_id]['quantity'] += $request->quantity;
            } else {
                $wishlist[$product->product_id] = [
                    'quantity' => $request->quantity,
                    'price' => $product->price_after_discount ?? $product->unit_price
                ];
            }

            session(['wishlist' => $wishlist]);
        }

        return redirect()->route('wishlist')->with('success', 'Product added to wishlist');
    }

    /**
     * Transfer session wishlist to database after login
     *
     * @param string $userId
     * @return void
     */
    public function transferSessionWishlistToDatabase($userId)
    {
        if (session()->has('wishlist')) {
            $sessionWishlist = session('wishlist');
            $wishlist = Wishlist::firstOrCreate(
                ['user_id' => $userId],
                [
                    'wishlist_id' => 'wishlist' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'creation_date' => now()
                ]
            );

            // Ensure wishlist_id is set
            if (!$wishlist->wishlist_id) {
                $wishlist->wishlist_id = 'wishlist' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $wishlist->creation_date = now();
                $wishlist->save();
            }

            // Delete existing wishlist items to avoid duplicates
            foreach ($sessionWishlist as $productId => $item) {
                \DB::statement('DELETE FROM WISHLIST_PRODUCT WHERE wishlist_id = :wishlist_id AND product_id = :product_id', [
                    'wishlist_id' => $wishlist->wishlist_id,
                    'product_id' => $productId
                ]);
            }

            // Insert new wishlist items
            foreach ($sessionWishlist as $productId => $item) {
                $product = Product::find($productId);
                if ($product) {
                    \DB::statement('INSERT INTO WISHLIST_PRODUCT (wishlist_id, product_id, product_quantity) VALUES (:wishlist_id, :product_id, :quantity)', [
                        'wishlist_id' => $wishlist->wishlist_id,
                        'product_id' => $productId,
                        'quantity' => $item['quantity']
                    ]);
                }
            }

            session()->forget('wishlist');
        }
    }

    /**
     * Update product quantity in the wishlist
     *
     * Handles AJAX requests to update item quantity
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateWishlist(Request $request)
    {
        try {
            Log::info('Wishlist update request:', [
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'user_session' => session()->has('user_id')
            ]);

            $request->validate([
                'product_id' => 'required|exists:PRODUCT,product_id',
                'quantity' => 'required|integer|min:1'
            ]);

            $product = Product::findOrFail($request->product_id);

            // Check stock availability
            if ($product->stock < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock available'
                ]);
            }

            // Check 20-item wishlist limit
            if (session()->has('user_id')) {
                $userId = session('user_id');
                $wishlist = Wishlist::where('user_id', $userId)->first();

                if ($wishlist) {
                    // Calculate total quantity excluding the current product
                    $otherProductsQuantity = DB::table('WISHLIST_PRODUCT')
                        ->where('wishlist_id', $wishlist->wishlist_id)
                        ->where('product_id', '!=', $product->product_id)
                        ->sum('product_quantity');

                    if (($otherProductsQuantity + $request->quantity) > 20) {
                        $maxAllowed = 20 - $otherProductsQuantity;
                        return response()->json([
                            'success' => false,
                            'message' => "Maximum {$maxAllowed} items allowed for this product. Wishlist limit is 20 items total."
                        ]);
                    }

                    // Update quantity
                    $updateResult = DB::table('WISHLIST_PRODUCT')
                        ->where('wishlist_id', $wishlist->wishlist_id)
                        ->where('product_id', $product->product_id)
                        ->update([
                            'product_quantity' => $request->quantity
                        ]);

                    Log::info('Database update result:', ['affected_rows' => $updateResult]);
                }
            } else {
                // Guest user - update session
                $wishlist = session('wishlist', []);

                // Calculate total quantity excluding the current product
                $otherProductsQuantity = 0;
                foreach ($wishlist as $productId => $item) {
                    if ($productId != $product->product_id) {
                        $otherProductsQuantity += $item['quantity'];
                    }
                }

                if (($otherProductsQuantity + $request->quantity) > 20) {
                    $maxAllowed = 20 - $otherProductsQuantity;
                    return response()->json([
                        'success' => false,
                        'message' => "Maximum {$maxAllowed} items allowed for this product. Wishlist limit is 20 items total."
                    ]);
                }

                if (isset($wishlist[$product->product_id])) {
                    $wishlist[$product->product_id]['quantity'] = $request->quantity;
                    session(['wishlist' => $wishlist]);
                }
            }

            $updatedTotals = $this->getWishlistTotals();
            Log::info('Updated totals calculated successfully');

            return response()->json([
                'success' => true,
                'message' => 'Wishlist updated successfully',
                'updated_totals' => $updatedTotals
            ]);

        } catch (\Exception $e) {
            Log::error('Wishlist update error:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the wishlist: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Remove a product from the wishlist
     *
     * Handles AJAX requests to remove items from wishlist
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeFromWishlist(Request $request)
    {
        try {
            Log::info('Wishlist removal request started:', $request->all());

            $request->validate([
                'product_id' => 'required|exists:PRODUCT,product_id'
            ]);

            $productId = $request->product_id;

            if (session()->has('user_id')) {
                $userId = session('user_id');
                $wishlist = Wishlist::where('user_id', $userId)->first();

                if ($wishlist) {
                    $deleteResult = DB::table('WISHLIST_PRODUCT')
                        ->where('wishlist_id', $wishlist->wishlist_id)
                        ->where('product_id', $productId)
                        ->delete();

                    Log::info('Item removal result:', ['deleted_rows' => $deleteResult, 'product_id' => $productId]);
                }
            } else {
                // Guest user - remove from session
                $wishlist = session('wishlist', []);

                if (isset($wishlist[$productId])) {
                    unset($wishlist[$productId]);
                    session(['wishlist' => $wishlist]);
                    Log::info('Item removed from session wishlist:', ['product_id' => $productId]);
                }
            }

            $updatedTotals = $this->getWishlistTotals();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from wishlist',
                'updated_totals' => $updatedTotals
            ]);

        } catch (\Exception $e) {
            Log::error('Wishlist removal error:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'product_id' => $request->product_id ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while removing the item: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get current wishlist totals and information
     * Used for AJAX updates to refresh wishlist display
     *
     * @return array
     */
    private function getWishlistTotals()
    {
        try {
            $wishlistItems = [];
            $subtotal = 0;
            $totalQuantity = 0;

            if (session()->has('user_id')) {
                $userId = session('user_id');
                $wishlist = Wishlist::with('products')->where('user_id', $userId)->first();

                if ($wishlist) {
                    foreach ($wishlist->products as $product) {
                        if (!$product) {
                            continue; // Skip if null
                        }

                        $quantity = $product->pivot->product_quantity ?? 0;
                        $price = $product->price_after_discount ?? $product->unit_price;
                        $itemTotal = $price * $quantity;

                        $wishlistItems[] = [
                            'id' => $product->product_id,
                            'name' => $product->product_name,
                            'price' => $price,
                            'quantity' => $quantity,
                            'item_total' => $itemTotal,
                            'image' => route('trader.product.image', $product->product_id),
                            'stock' => $product->stock
                        ];

                        $subtotal += $itemTotal;
                        $totalQuantity += $quantity;
                    }
                }
            } else {
                // Guest user - get wishlist from session
                $sessionWishlist = session('wishlist', []);

                foreach ($sessionWishlist as $productId => $item) {
                    $product = Product::find($productId);
                    if ($product) {
                        $quantity = $item['quantity'];
                        $price = $product->price_after_discount ?? $product->unit_price;
                        $itemTotal = $price * $quantity;

                        $wishlistItems[] = [
                            'id' => $product->product_id,
                            'name' => $product->product_name,
                            'price' => $price,
                            'quantity' => $quantity,
                            'item_total' => $itemTotal,
                            'image' => route('trader.product.image', $product->product_id),
                            'stock' => $product->stock
                        ];

                        $subtotal += $itemTotal;
                        $totalQuantity += $quantity;
                    }
                }
            }

            return [
                'items' => $wishlistItems,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'total_quantity' => $totalQuantity,
                'remaining_items' => 20 - $totalQuantity,
                'formatted_subtotal' => number_format($subtotal, 2),
                'formatted_total' => number_format($subtotal, 2)
            ];

        } catch (\Exception $e) {
            Log::error('Error calculating wishlist totals:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return [
                'items' => [],
                'subtotal' => 0,
                'total' => 0,
                'total_quantity' => 0,
                'remaining_items' => 20,
                'formatted_subtotal' => '0.00',
                'formatted_total' => '0.00'
            ];
        }
    }

    /**
     * Simple test method for debugging wishlist updates
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testUpdate(Request $request)
    {
        try {
            Log::info('Test wishlist update called with data:', $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Test successful',
                'data' => $request->all(),
                'session_has_user' => session()->has('user_id'),
                'session_user_id' => session('user_id')
            ]);
        } catch (\Exception $e) {
            Log::error('Test update error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Simple test method for debugging wishlist removal
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testRemove(Request $request)
    {
        try {
            Log::info('Test wishlist remove called with data:', $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Test remove successful',
                'data' => $request->all(),
                'session_has_user' => session()->has('user_id'),
                'session_user_id' => session('user_id')
            ]);
        } catch (\Exception $e) {
            Log::error('Test remove error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Test remove failed: ' . $e->getMessage()
            ]);
        }
    }
}