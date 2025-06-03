<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderInvoiceMail;
use Illuminate\Support\Facades\Log ;
use App\Models\Cart;  


class CheckoutController extends Controller
{
    public function createTransaction(Request $request)
    {
        // Validate pickup slot data
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'pickup_date' => 'required|date_format:Y-m-d',
            'pickup_slot' => 'required|in:10-13,13-16,16-19',
            'slot_id' => 'required|string'
        ]);

        // Validate pickup slot is valid (Wed, Thu, Fri and at least 24 hours in advance)
        $pickupDate = strtotime($request->pickup_date);
        $dayOfWeek = date('w', $pickupDate);
        $minDate = strtotime('+24 hours');
        
        if ($dayOfWeek < 3 || $dayOfWeek > 5) {
            return redirect()->route('cart')->with('error', 'Pickup is only available on Wednesday, Thursday, and Friday.');
        }
        
        if ($pickupDate < $minDate) {
            return redirect()->route('cart')->with('error', 'Pickup must be scheduled at least 24 hours in advance.');
        }

        // Store pickup slot data in session for later use
        session([
            'pickup_data' => [
                'pickup_date' => $request->pickup_date,
                'pickup_slot' => $request->pickup_slot,
                'slot_id' => $request->slot_id
            ]
        ]);

        // Final validation: Check if the slot is still available (max 20 orders per slot)
        $currentOrderCount = DB::table('COLLECTION_SLOT')
            ->where('slot_id', $request->slot_id)
            ->value('no_order') ?? 0;
            
        if ($currentOrderCount >= 20) {
            return redirect()->route('cart')->with('error', 'Sorry, this pickup slot is now fully booked. Please select a different time slot.');
        }

        $paypal = new PayPalClient;
        $paypal->setApiCredentials(config('paypal'));
        $token = $paypal->getAccessToken();
        $paypal->setAccessToken($token);

        
        $response = $paypal->createOrder([
            "intent" => "CAPTURE",
            "application_context" => [
                "return_url" => route('paypal.success'),
                "cancel_url" => route('paypal.cancel'),
            ],
            "purchase_units" => [
                [
                    "reference_id" => Str::uuid(),
                    "amount" => [
                        "currency_code" => env('PAYPAL_CURRENCY', 'USD'),
                        "value" => $request->amount
                    ]
                ]
            ]
        ]);

        if (isset($response['id']) && $response['status'] === 'CREATED') {
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    return redirect()->away($link['href']);
                }
            }
        }

        return redirect()->route('cart')->with('error', 'Something went wrong.');
    }


public function successTransaction(Request $request)
{
    $paypal = new PayPalClient;
    $paypal->setApiCredentials(config('paypal'));
    $token = $paypal->getAccessToken();
    $paypal->setAccessToken($token);

    $response = $paypal->capturePaymentOrder($request->token);

    if (isset($response['status']) && $response['status'] === 'COMPLETED') {
        $userId = session(key: 'user_id');

        // Get capture details
        $capture = $response['purchase_units'][0]['payments']['captures'][0];
        $amount = $capture['amount']['value'];

        $paymentId = strtoupper(Str::random(8));
        $orderId = strtoupper(Str::random(8)); // generate new order ID

        $cart = DB::table('CART')->where('USER_ID', $userId)->first();
        if (!$cart) {
            return redirect()->route('cart')->with('error', 'Cart not found.');
        }

        $cartProducts = DB::table('CART_PRODUCT')->where('CART_ID', $cart->cart_id)->get();
        if ($cartProducts->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Cart is empty.');
        }

        // Get pickup slot data from session
        $pickupData = session('pickup_data');
        if (!$pickupData) {
            return redirect()->route('cart')->with('error', 'Pickup slot information is missing. Please select a pickup slot and try again.');
        }

        $slotId = $pickupData['slot_id'];
        $couponId = null;

        try {
            // Begin database transaction to ensure data consistency
            DB::beginTransaction();

            // Insert order
            DB::table('ORDER1')->insert([
                'ORDER_ID' => $orderId,
                'ORDER_DATE' => now(),
                'COUPON_ID' => $couponId,
                'CART_ID' => $cart->cart_id,
                'PAYMENT_AMOUNT' => $amount,
                'SLOT_ID' => $slotId,
                'USER_ID' => $userId
            ]);

            $order = DB::table('ORDER1')
                ->where('USER_ID', $userId)
                ->orderByDesc('ORDER_DATE')
                ->first();

            if (!$order) {
                DB::rollBack();
                return redirect()->route('cart')->with('error', 'Order not found.');
            }

            $orderId = $order->order_id;
            // Insert order items
            foreach ($cartProducts as $item) {
                // Fetch product to get price after discount or unit price
                $product = DB::table('PRODUCT')->where('PRODUCT_ID', $item->product_id)->first();

                $unitPrice = $product->price_after_discount ?? $product->unit_price ?? 0;
                $quantity = $item->product_quantity ?? 1;

                DB::table('ORDER_ITEM')->insert([
                    'ORDER_ITEM_ID' => strtoupper(Str::random(10)),
                    'ORDER_ID' => $orderId,
                    'PRODUCT_ID' => $item->product_id,
                    'QUANTITY' => $quantity,
                    'UNIT_PRICE' => $unitPrice,
                ]);
            }

            DB::table('ORDER_STATUS')->insert([
                'ORDER_ID' => $orderId,
                'STATUS' => 'pending',
                'CREATED_AT' => now(),
                'UPDATED_AT' => now()
            ]);

            // Insert payment
            DB::table('PAYMENT')->insert([
                'PAYMENT_ID' => $paymentId,
                'PAYMENT_METHOD' => 'PayPal',
                'PAYMENT_DATE' => now(),
                'USER_ID' => $userId,
                'ORDER_ID' => $orderId,
                'PAYMENT_AMOUNT' => $amount
            ]);

            // CRITICAL FIX: Clear the cart after successful order creation
            $cartClearResult = DB::table('CART_PRODUCT')->where('CART_ID', $cart->cart_id)->delete();
            Log::info('Cart cleared after successful payment', [
                'cart_id' => $cart->cart_id,
                'deleted_rows' => $cartClearResult,
                'order_id' => $orderId
            ]);

            // Also clear session cart if it exists (for consistency)
            if (session()->has('cart')) {
                session()->forget('cart');
                Log::info('Session cart cleared after successful payment', [
                    'order_id' => $orderId,
                    'user_id' => $userId
                ]);
            }

            // Clear pickup slot data from session
            if (session()->has('pickup_data')) {
                session()->forget('pickup_data');
                Log::info('Pickup slot data cleared from session', [
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'pickup_slot_id' => $slotId
                ]);
            }

            // Send invoice email to customer
            try {
                // Get customer email from database
                $user = DB::table('USER1')->where('user_id', $userId)->first();
                if ($user && $user->email) {
                    // Get order items for email
                    $emailOrderItems = DB::table('ORDER_ITEM')
                        ->join('PRODUCT', 'ORDER_ITEM.product_id', '=', 'PRODUCT.product_id')
                        ->where('ORDER_ITEM.order_id', $orderId)
                        ->select(
                            'ORDER_ITEM.*',
                            'PRODUCT.product_name',
                            'PRODUCT.unit_price'
                        )
                        ->get();
                    
                    // Get order details for email
                    $emailOrder = DB::table('ORDER1')
                        ->join('ORDER_STATUS', 'ORDER1.order_id', '=', 'ORDER_STATUS.order_id')
                        ->leftJoin('COLLECTION_SLOT', 'ORDER1.slot_id', '=', 'COLLECTION_SLOT.slot_id')
                        ->where('ORDER1.order_id', $orderId)
                        ->select(
                            'ORDER1.*',
                            'ORDER_STATUS.status',
                            'COLLECTION_SLOT.day as pickup_date',
                            'COLLECTION_SLOT.time as pickup_time'
                        )
                        ->first();

                    // Send the invoice email
                    Mail::to($user->email)->send(new OrderInvoiceMail($emailOrder, $emailOrderItems, $user->email));
                    
                    Log::info('Invoice email sent successfully', [
                        'order_id' => $orderId,
                        'user_id' => $userId,
                        'email' => $user->email
                    ]);
                } else {
                    Log::warning('Could not send invoice email - user email not found', [
                        'order_id' => $orderId,
                        'user_id' => $userId
                    ]);
                }
            } catch (\Exception $emailException) {
                Log::error('Failed to send invoice email', [
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'error' => $emailException->getMessage()
                ]);
                // Don't fail the entire transaction if email fails
            }

            // Increment the collection slot order count (inside transaction for atomicity)
            DB::table('COLLECTION_SLOT')
                ->where('slot_id', $slotId)
                ->increment('no_order');
            
            Log::info('Collection slot order count incremented', [
                'slot_id' => $slotId,
                'order_id' => $orderId
            ]);

            // Commit all database changes
            DB::commit();

            // Redirect to success page instead of back to cart
            return redirect()->route('order.success', ['order_id' => $orderId])
                ->with('success', 'Payment successful! Your order has been placed. Your cart has been cleared.');

        } catch (\Exception $e) {
            // Rollback transaction on any error
            DB::rollBack();
            Log::error('Order creation failed during payment success', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'cart_id' => $cart->cart_id
            ]);
            return redirect()->route('cart')->with('error', 'Order processing failed. Please contact support.');
        }
    }

    return redirect()->route('cart')->with('error', 'Payment failed.');
}
    
    public function cancelTransaction()
    {
        return redirect()->route('cart')->with('error', 'Payment was cancelled.');
    }

    /**
     * Display order success page
     */
    public function orderSuccess($orderId)
    {
        $order = DB::table('ORDER1')
            ->join('ORDER_STATUS', 'ORDER1.order_id', '=', 'ORDER_STATUS.order_id')
            ->leftJoin('COLLECTION_SLOT', 'ORDER1.slot_id', '=', 'COLLECTION_SLOT.slot_id')
            ->where('ORDER1.order_id', $orderId)
            ->select(
                'ORDER1.*',
                'ORDER_STATUS.status',
                'COLLECTION_SLOT.day as pickup_date',
                'COLLECTION_SLOT.time as pickup_time'
            )
            ->first();

        if (!$order) {
            return redirect()->route('home')->with('error', 'Order not found');
        }

        $orderItems = DB::table('ORDER_ITEM')
            ->join('PRODUCT', 'ORDER_ITEM.product_id', '=', 'PRODUCT.product_id')
            ->where('ORDER_ITEM.order_id', $orderId)
            ->select(
                'ORDER_ITEM.*',
                'PRODUCT.product_name',
                'PRODUCT.product_image'
            )
            ->get();

        return view('order-success', compact('order', 'orderItems'));
    }
}