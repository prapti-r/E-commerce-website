<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CartController
 * 
 * Handles all shopping cart related functionality including:
 * - Displaying cart contents
 * - Adding/removing/updating items
 * - Managing pickup slots
 * - Transferring session cart to database after login
 */
class CartController extends Controller
{
    /**
     * Display the shopping cart page with all items
     * 
     * For authenticated users, retrieves cart from database
     * For guest users, retrieves cart from session
     * 
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $cartItems = [];
        $subtotal = 0;
            
        if (session()->has('user_id')) {
            $userId = session('user_id'); 
            $cart = Cart::with('products')->where('user_id', $userId)->first();

        if ($cart) {
            foreach ($cart->products as $product) {
                if (!$product) {
            continue; // Skip if null
        }

                $quantity = $product->pivot->product_quantity ?? 0;
                $cartItems[] = [
                    'id' => $product->product_id,
                    'name' => $product->product_name,
                    'price' => $product->price_after_discount ?? $product->unit_price,
                    'quantity' => $quantity,
                    'image' => route('trader.product.image', $product->product_id),
                    'stock' => $product->stock
                ];
                $subtotal += ($product->price_after_discount ?? $product->unit_price) * $quantity;
            }
        }
    } else {
        // Guest user - get cart from session
        $sessionCart = session('cart', []);
        
        foreach ($sessionCart as $productId => $item) {
            $product = Product::find($productId);
            if ($product) {
                $cartItems[] = [
                    'id' => $product->product_id,
                    'name' => $product->product_name,
                    'price' => $product->price_after_discount ?? $product->unit_price,
                    'quantity' => $item['quantity'],
                    'image' => route('trader.product.image', $product->product_id),
                    'stock' => $product->stock
                ];
                $subtotal += ($product->price_after_discount ?? $product->unit_price) * $item['quantity'];
            }
        }
    }

        return view('cart', [
            'items' => $cartItems,
            'subtotal' => $subtotal,
            'total' => $subtotal, // Will add discounts later
            'isAuthenticated' => session()->has('user_id')
        ]);
    }

    public function addToCart(Request $request)
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

        // Calculate current cart total quantity
        $currentCartQuantity = 0;
        
        if (session()->has('user_id')) {
            $userId = session('user_id');
            $cart = Cart::where('user_id', $userId)->first();
            
            if ($cart) {
                $currentCartQuantity = DB::table('CART_PRODUCT')
                    ->where('cart_id', $cart->cart_id)
                    ->sum('product_quantity');
            }
        } else {
            // Guest user - check session cart
            $sessionCart = session('cart', []);
            foreach ($sessionCart as $item) {
                $currentCartQuantity += $item['quantity'];
            }
        }

        // Check if adding this quantity would exceed the 20 item limit
        $newTotalQuantity = $currentCartQuantity + $request->quantity;
        if ($newTotalQuantity > 20) {
            $remainingSpace = 20 - $currentCartQuantity;
            if ($remainingSpace <= 0) {
                return back()->with('error', 'Your cart is full! Maximum 20 items allowed per cart.');
            } else {
                return back()->with('error', "You can only add {$remainingSpace} more item(s) to your cart. Maximum 20 items allowed per cart.");
            }
        }

        if (session()->has('user_id')) {
    $userId = session('user_id');

    // Get or create user's cart
    $cart = Cart::firstOrCreate(
        ['user_id' => $userId],
        ['cart_id' => 'cart' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT)]
    );

    $price = $product->price_after_discount ?? $product->unit_price;

    // Find existing cart product
        $cartProduct = CartProduct::where('cart_id', $cart->cart_id)
        ->where('product_id', $product->product_id)
        ->first();

    if ($cartProduct) {
        // Calculate new quantities
        $newQuantity = $cartProduct->product_quantity + $request->quantity;
        $newTotal = $price * $newQuantity;

        // Double-check quantity limit for existing products
        $otherProductsQuantity = DB::table('CART_PRODUCT')
            ->where('cart_id', $cart->cart_id)
            ->where('product_id', '!=', $product->product_id)
            ->sum('product_quantity');
        
        if (($otherProductsQuantity + $newQuantity) > 20) {
            $maxAllowed = 20 - $otherProductsQuantity;
            return back()->with('error', "You can only add {$maxAllowed} more of this item. Maximum 20 items allowed per cart.");
        }

        // Delete old row to avoid update trigger
        $cartProduct->delete();

        // Insert new row with updated quantity and total
        CartProduct::create([
            'cart_id' => $cart->cart_id,
            'product_id' => $product->product_id,
            'product_quantity' => $newQuantity,
            'total_amount' => $newTotal
        ]);
    } else {
        // Insert new row
        CartProduct::create([
            'cart_id' => $cart->cart_id,
            'product_id' => $product->product_id,
            'product_quantity' => $request->quantity,
            'total_amount' => $price * $request->quantity
        ]);
    }


        } else {
            // Guest user - store in session
            $cart = session('cart', []);
            
            if (isset($cart[$product->product_id])) {
                $cart[$product->product_id]['quantity'] += $request->quantity;
            } else {
                $cart[$product->product_id] = [
                    'quantity' => $request->quantity,
                    'price' => $product->price_after_discount ?? $product->unit_price
                ];
            }
            
            session(['cart' => $cart]);
        }

        return redirect()->route('cart')->with('success', 'Product added to cart');
    }

    // Add this method to transfer session cart to database after login
    public function transferSessionCartToDatabase($userId)
    {
        if (session()->has('cart')) {
            $sessionCart = session('cart');
            $cart = Cart::firstOrCreate(
            ['user_id' => $userId],
            ['cart_id' => 'cart' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT)]
        );

        // Make sure cart_id is not null (in case the existing row was missing it)
        if (!$cart->cart_id) {
            $cart->cart_id = 'cart' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $cart->save();
        }

            // Delete any existing cart items that might be in the session cart
            // to avoid the trigger issue
            foreach ($sessionCart as $productId => $item) {
                \DB::statement('DELETE FROM CART_PRODUCT WHERE cart_id = :cart_id AND product_id = :product_id', [
                    'cart_id' => $cart->cart_id,
                    'product_id' => $productId
                ]);
            }
            
            // Now we can safely insert new items
            foreach ($sessionCart as $productId => $item) {
                $product = Product::find($productId);
                if ($product) {
                    $quantity = $item['quantity'];
                    $price = $product->price_after_discount ?? $product->unit_price;
                    $totalAmount = $quantity * $price;

                    // Insert directly with a simple statement to avoid trigger issues
                    \DB::statement('INSERT INTO CART_PRODUCT (cart_id, product_id, product_quantity, total_amount) VALUES (:cart_id, :product_id, :quantity, :total)', [
                        'cart_id' => $cart->cart_id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'total' => $totalAmount
                    ]);
                }
            }

            session()->forget('cart');
        }
    }



    /**
     * Update product quantity in the cart
     * 
     * Handles AJAX requests to update item quantity
     * Validates product exists and has sufficient stock
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCart(Request $request)
    {
        try {
            // Log the incoming request for debugging
            \Log::info('Cart update request:', [
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

            // Check 20-item cart limit
            if (session()->has('user_id')) {
            $userId = session('user_id'); 
            $cart = Cart::where('user_id', $userId)->first();

                if ($cart) {
                    // Calculate total quantity excluding the current product
                    $otherProductsQuantity = DB::table('CART_PRODUCT')
                        ->where('cart_id', $cart->cart_id)
                        ->where('product_id', '!=', $product->product_id)
                        ->sum('product_quantity');
                    
                    if (($otherProductsQuantity + $request->quantity) > 20) {
                        $maxAllowed = 20 - $otherProductsQuantity;
                        return response()->json([
                            'success' => false,
                            'message' => "Maximum {$maxAllowed} items allowed for this product. Cart limit is 20 items total."
                        ]);
                    }

                    // Use direct DB update to avoid potential model issues
                    $updateResult = DB::table('CART_PRODUCT')
                        ->where('cart_id', $cart->cart_id)
                    ->where('product_id', $product->product_id)
                    ->update([
                        'product_quantity' => $request->quantity,
                        'total_amount' => ($product->price_after_discount ?? $product->unit_price) * $request->quantity
                    ]);

                    \Log::info('Database update result:', ['affected_rows' => $updateResult]);
            }
        } else {
            // Guest user - update session
            $cart = session('cart', []);
                
                // Calculate total quantity excluding the current product
                $otherProductsQuantity = 0;
                foreach ($cart as $productId => $item) {
                    if ($productId != $product->product_id) {
                        $otherProductsQuantity += $item['quantity'];
                    }
                }
                
                if (($otherProductsQuantity + $request->quantity) > 20) {
                    $maxAllowed = 20 - $otherProductsQuantity;
                    return response()->json([
                        'success' => false,
                        'message' => "Maximum {$maxAllowed} items allowed for this product. Cart limit is 20 items total."
                    ]);
                }
            
            if (isset($cart[$product->product_id])) {
                $cart[$product->product_id]['quantity'] = $request->quantity;
                    session(['cart' => $cart]);
                }
            }
            
            $updatedTotals = $this->getCartTotals();
            \Log::info('Updated totals calculated successfully');

        return response()->json([
            'success' => true,
                'message' => 'Cart updated successfully',
                'updated_totals' => $updatedTotals
            ]);

        } catch (\Exception $e) {
            \Log::error('Cart update error:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the cart: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Remove a product from the cart
     * 
     * Handles AJAX requests to remove items from cart
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeFromCart(Request $request)
    {
        try {
            \Log::info('Cart removal request started:', $request->all());

            $request->validate([
                'product_id' => 'required|exists:PRODUCT,product_id',
            ]);

            $productId = $request->product_id;

            if (session()->has('user_id')) {
                $userId = session('user_id'); 
                $cart = Cart::where('user_id', $userId)->first();
                
                if ($cart) {
                    $deleteResult = DB::table('CART_PRODUCT')
                        ->where('cart_id', $cart->cart_id)
                        ->where('product_id', $productId)
                        ->delete();
                    
                    \Log::info('Item removal result:', ['deleted_rows' => $deleteResult, 'product_id' => $productId]);
                }
            } else {
                // Guest user - remove from session
                $cart = session('cart', []);
                
                if (isset($cart[$productId])) {
                    unset($cart[$productId]);
                    session(['cart' => $cart]);
                    \Log::info('Item removed from session cart:', ['product_id' => $productId]);
                }
            }

            // Calculate updated totals
            try {
                $updatedTotals = $this->getCartTotals();
                \Log::info('Cart totals calculated after removal:', [
                    'total_quantity' => $updatedTotals['total_quantity'],
                    'total' => $updatedTotals['total']
                ]);
            } catch (\Exception $totalsError) {
                \Log::error('Error calculating cart totals after removal:', [
                    'error' => $totalsError->getMessage(),
                    'product_id' => $productId
                ]);
                
                // Return a safe response even if totals calculation fails
                return response()->json([
                    'success' => true,
                    'message' => 'Item removed from cart',
                    'updated_totals' => [
                        'items' => [],
                        'subtotal' => 0,
                        'total' => 0,
                        'total_quantity' => 0,
                        'remaining_items' => 20,
                        'formatted_subtotal' => '0.00',
                        'formatted_total' => '0.00'
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart',
                'updated_totals' => $updatedTotals
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Cart removal error:', [
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
     * Check availability of a pickup slot
     * 
     * Validates that:
     * - Date is a valid pickup day (Wed, Thu, Fri)
     * - Date is at least 24 hours in advance
     * - Slot has available capacity (max 20 orders)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkSlotAvailability(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'slot' => 'required|in:10-13,13-16,16-19'
        ]);

        $date = $request->date;
        $slot = $request->slot;
        
        // Check if date is valid (Wed, Thu, Fri)
        $dayOfWeek = date('w', strtotime($date));
        if ($dayOfWeek < 3 || $dayOfWeek > 5) {
            return response()->json([
                'success' => false,
                'message' => 'Pickup is only available on Wednesday, Thursday, and Friday.'
            ]);
        }
        
        // Check if date is at least 24 hours in advance
        $pickupDate = strtotime($date);
        $minDate = strtotime('+24 hours');
        if ($pickupDate < $minDate) {
            return response()->json([
                'success' => false,
                'message' => 'Pickup must be scheduled at least 24 hours in advance.'
            ]);
        }
        
        // Get or create the slot and check capacity
        $slotId = $this->getOrCreateSlotId($date, $slot);
        
        // Get current order count for this slot
        $currentOrderCount = DB::table('COLLECTION_SLOT')
            ->where('slot_id', $slotId)
            ->value('no_order') ?? 0;
            
        $maxOrders = 20;
        $remaining = $maxOrders - $currentOrderCount;
        $available = $remaining > 0;
        
        if (!$available) {
            return response()->json([
                'success' => true,
                'available' => false,
                'remaining' => 0,
                'total' => $maxOrders,
                'slot_id' => $slotId,
                'message' => 'This time slot is fully booked (20/20 orders). Please select a different time slot.'
            ]);
        }
        
        return response()->json([
            'success' => true,
            'available' => true,
            'remaining' => $remaining,
            'total' => $maxOrders,
            'slot_id' => $slotId,
            'message' => $remaining === 1 ? 'Last slot available!' : "{$remaining} slots remaining"
        ]);
    }
    
    /**
     * Get or create a slot ID for a given date and time slot
     * 
     * @param string $date Date in Y-m-d format
     * @param string $timeSlot Time slot string (e.g., '10-13')
     * @return string The slot ID
     */
    private function getOrCreateSlotId($date, $timeSlot)
    {
        // Format: date_timeSlot (e.g., 2025-06-11_10-13)
        $slotKey = $date . '_' . $timeSlot;
        
        // Check if slot exists
        $slot = \DB::table('COLLECTION_SLOT')
            ->where('day', $date)
            ->where('time', $this->getSlotTime($date, $timeSlot))
            ->first();
            
        if ($slot) {
            return $slot->slot_id;
        }
        
        // Create new slot
        $slotId = 'slot' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        \DB::table('COLLECTION_SLOT')->insert([
            'slot_id' => $slotId,
            'day' => $date,
            'time' => $this->getSlotTime($date, $timeSlot),
            'no_order' => 0
        ]);
        
        return $slotId;
    }
    
    /**
     * Convert a slot string to a timestamp
     * 
     * @param string $date Date in Y-m-d format
     * @param string $timeSlot Time slot string (e.g., '10-13')
     * @return string Formatted timestamp
     */
    private function getSlotTime($date, $timeSlot)
    {
        $startHour = explode('-', $timeSlot)[0];
        return date('Y-m-d H:i:s', strtotime("$date $startHour:00:00"));
    }

    /**
     * Get current cart totals and information
     * Used for AJAX updates to refresh cart display
     */
    private function getCartTotals()
    {
        try {
            $cartItems = [];
            $subtotal = 0;
            $totalQuantity = 0;
                
            if (session()->has('user_id')) {
                $userId = session('user_id'); 
                $cart = Cart::with('products')->where('user_id', $userId)->first();

                if ($cart) {
                    foreach ($cart->products as $product) {
                        if (!$product) {
                            continue; // Skip if null
                        }

                        $quantity = $product->pivot->product_quantity ?? 0;
                        $price = $product->price_after_discount ?? $product->unit_price;
                        $itemTotal = $price * $quantity;
                        
                        $cartItems[] = [
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
                // Guest user - get cart from session
                $sessionCart = session('cart', []);
                
                foreach ($sessionCart as $productId => $item) {
                    $product = Product::find($productId);
                    if ($product) {
                        $price = $product->price_after_discount ?? $product->unit_price;
                        $quantity = $item['quantity'];
                        $itemTotal = $price * $quantity;
                        
                        $cartItems[] = [
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
                'items' => $cartItems,
                'subtotal' => $subtotal,
                'total' => $subtotal, // Will add discounts later
                'total_quantity' => $totalQuantity,
                'remaining_items' => 20 - $totalQuantity,
                'formatted_subtotal' => number_format($subtotal, 2),
                'formatted_total' => number_format($subtotal, 2)
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error calculating cart totals:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            // Return safe default values
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
     * Simple test method for debugging cart updates
     */
    public function testUpdate(Request $request)
    {
        try {
            \Log::info('Test update called with data:', $request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Test successful',
                'data' => $request->all(),
                'session_has_user' => session()->has('user_id'),
                'session_user_id' => session('user_id')
            ]);
        } catch (\Exception $e) {
            \Log::error('Test update error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Simple test method for debugging cart removal
     */
    public function testRemove(Request $request)
    {
        try {
            \Log::info('Test remove called with data:', $request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Test remove successful',
                'data' => $request->all(),
                'session_has_user' => session()->has('user_id'),
                'session_user_id' => session('user_id')
            ]);
        } catch (\Exception $e) {
            \Log::error('Test remove error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Test remove failed: ' . $e->getMessage()
            ]);
        }
    }

    // ... rest of your methods ...
}