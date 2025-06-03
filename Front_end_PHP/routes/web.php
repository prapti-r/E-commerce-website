<?php

use App\Http\Controllers\VendorController;
use App\Http\Controllers\User1Controller;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProfileController;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\WishlistController;

/**
 * Public Routes
 * These routes are accessible to all users without authentication
 */

// Main public pages
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::get('/contact', [ContactController::class, 'showContactForm'])->name('contact');

// Authentication routes
Route::get('/signup', [AuthController::class, 'showSignupForm'])->name('signup');
Route::post('/signup', [AuthController::class, 'signup'])->name('signup.submit');
Route::get('/verify-otp', [AuthController::class, 'showVerifyOtpForm'])->name('verify.otp.form');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('verify.otp');
Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->name('resend.otp');
Route::get('/signin', [AuthController::class, 'showSigninForm'])->name('signin');
Route::post('/signin', [AuthController::class, 'signin'])->name('signin.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile');

// Product and category browsing
Route::get('/products/{id}', [ProductController::class, 'show'])->name('product.detail');
Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

/**
 * Trader Dashboard Routes
 * These routes are for trader-specific functionality
 */

// Main trader dashboard pages
Route::get('/trader', [VendorController::class, 'dashboard'])->name('trader');
Route::get('/trader_order', [VendorController::class, 'orders'])->name('Trader Order');
Route::get('/trader_product', [VendorController::class, 'products'])->name('Trader Product');
Route::get('/trader_analytics', [VendorController::class, 'analytics'])->name('Trader Analytics');
Route::post('/trader/update-shop', [VendorController::class, 'updateShop'])->name('trader.updateShop');

// Trader additional pages and analytics
Route::get('/trader/sales', [VendorController::class, 'analytics'])->name('trader.sales');
Route::get('/trader/reviews', [VendorController::class, 'reviews'])->name('trader.reviews');
Route::get('/trader/analytics/period/{period}', [VendorController::class, 'analyticsByPeriod'])->name('trader.analytics.period');

// Product Management Routes
Route::post('/trader/products', [VendorController::class, 'storeProduct'])->name('trader.products.store');
Route::get('/trader/products/{id}/edit', [VendorController::class, 'editProduct'])->name('trader.products.edit');
Route::put('/trader/products/{id}', [VendorController::class, 'updateProduct'])->name('trader.products.update');
Route::delete('/trader/products/{id}', [VendorController::class, 'deleteProduct'])->name('trader.products.delete');

// Order management routes
Route::post('/trader/orders/{orderId}/update-status', [VendorController::class, 'updateOrderStatus'])->name('trader.orders.update-status');
Route::get('/trader/orders/{orderId}/status-check', [VendorController::class, 'checkOrderStatus'])->name('trader.orders.status-check');

// Product Image Management Routes
Route::get('/trader/product-image/{id}', [VendorController::class, 'viewProductImage'])->name('trader.product.image');
Route::get('/trader/image-fix', [VendorController::class, 'showImageFixTool'])->name('trader.image.fix');
Route::post('/trader/image-fix/product', [VendorController::class, 'loadProductForImageFix'])->name('trader.image.fix.product');
Route::post('/trader/image-fix/upload', [VendorController::class, 'uploadImageFix'])->name('trader.image.fix.upload');
Route::post('/trader/image-fix/default', [VendorController::class, 'applyDefaultImageFix'])->name('trader.image.fix.default');

// Trader RFID routes for inventory management
Route::post('/trader/rfid/run-relay', [App\Http\Controllers\VendorController::class, 'runRfidRelay'])->name('trader.rfid.run-relay');
Route::post('/trader/rfid/stop-relay', [App\Http\Controllers\VendorController::class, 'stopRfidRelay'])->name('trader.rfid.stop-relay');

/**
 * Cart and Checkout Routes
 * These routes handle shopping cart functionality
 */
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::post('/cart/add', [CartController::class, 'addToCart'])->name('cart.add');
Route::post('/cart/update', [CartController::class, 'updateCart'])->name('cart.update');
Route::post('/cart/remove', [CartController::class, 'removeFromCart'])->name('cart.remove');
Route::post('/cart/check-slot', [CartController::class, 'checkSlotAvailability'])->name('cart.check-slot');
Route::post('/cart/set-slot', [CartController::class, 'setSlotId']);
Route::post('/cart/test-update', [CartController::class, 'testUpdate'])->name('cart.test-update');
Route::post('/cart/test-remove', [CartController::class, 'testRemove'])->name('cart.test-remove');

/**
 * User Profile Routes
 * These routes handle user profile management
 */
Route::get('/profile', [ProfileController::class, 'profile'])->name('profile');
Route::post('/profile/edit', [ProfileController::class, 'updateProfile'])->name('profile.update');
Route::get('/profile/edit', [ProfileController::class, 'editProfile'])->name('profile-edit');
Route::get('/profile/change-password', [ProfileController::class, 'showChangePasswordForm'])->name('profile.changepass');
Route::post('/profile/change-password', [ProfileController::class, 'changePassword'])->name('profile.change-password');
Route::get('/profile/image/{id}', [ProfileController::class, 'showProfileImage'])->name('profile.image');


Route::post('/paypal/transaction/create', [CheckoutController::class, 'createTransaction'])->name('paypal.create');
Route::get('/paypal/transaction/success', [CheckoutController::class, 'successTransaction'])->name('paypal.success');
Route::get('/paypal/transaction/cancel', [CheckoutController::class, 'cancelTransaction'])->name('paypal.cancel');
Route::get('/order/success/{order_id}', [CheckoutController::class, 'orderSuccess'])->name('order.success');


Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
Route::post('/wishlist/add', [WishlistController::class, 'addToWishlist'])->name('wishlist.add');
Route::post('/wishlist/update', [WishlistController::class, 'updateWishlist'])->name('wishlist.update');
Route::post('/wishlist/remove', [WishlistController::class, 'removeFromWishlist'])->name('wishlist.remove');
Route::post('/wishlist/test-update', [WishlistController::class, 'testUpdate'])->name('wishlist.testUpdate');
Route::post('/wishlist/test-remove', [WishlistController::class, 'testRemove'])->name('wishlist.testRemove');
/**
 * Legacy and Utility Routes
 * These routes are for development, testing, or legacy functionality
 */
Route::get('/signup_form', [User1Controller::class, 'showForm']);
Route::post('/user-form', [User1Controller::class, 'store']);

// Debug and diagnostic routes
Route::get('/debug/raw-product', [VendorController::class, 'showRawProduct'])->name('debug.raw-product');
Route::post('/debug/fix-image', [VendorController::class, 'fixProductImage'])->name('debug.fix-image');
Route::get('/debug/fix-image', [VendorController::class, 'fixProductImage'])->name('debug.fix-image.get');
Route::get('/debug/order-data', [VendorController::class, 'checkOrderData'])->name('debug.order-data');
Route::get('/trader/debug-images', [VendorController::class, 'debugProductImages'])->name('trader.debug-images');

// Test routes
Route::get('/test-oracle', function () {
    $results = DB::select('SELECT * FROM dual');
    return response()->json($results);
});
Route::get('/test-oracle-blob', [VendorController::class, 'testOracleBlob']);
Route::get('/test-email', function () {
    \Mail::to('shahprabesh777@gmail.com')->send(new OtpMail('123456'));
    return 'Email sent!';
});