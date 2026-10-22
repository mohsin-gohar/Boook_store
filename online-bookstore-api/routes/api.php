<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WishlistController;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Review;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
});

/*
 |--------------------------------------------------------------------------
 | API Routes
 |--------------------------------------------------------------------------
 |
 | Here is where you can register API routes for your application. These
 | routes are loaded by the ApiRouteServiceProvider and all of them will
 | be assigned to the "api" middleware group.
 |
 */

// ── Authentication ────────────────────────────────────────────────
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
// Short canonical aliases the client and the test suite use.
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// ── Public catalogue ──────────────────────────────────────────────
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Online Bookstore REST API is running smoothly',
        'framework' => 'Laravel '.app()->version(),
        'database' => config('database.default'),
        'counts' => [
            'books' => Book::count(),
            'categories' => Category::count(),
            'authors' => Author::count(),
            'reviews' => Review::count(),
        ],
        'timestamp' => now()->toIso8601String(),
    ]);
});
Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{book}', [BookController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/authors', [AuthorController::class, 'index']);
Route::get('/authors/{author}', [AuthorController::class, 'show']);
Route::get('/reviews', [ReviewController::class, 'index']);
Route::get('/books/{book}/reviews', [ReviewController::class, 'forBook']);
Route::get('/faqs', [SupportController::class, 'faqs']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Account
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateMe']);
    Route::put('/me/password', [AuthController::class, 'changePassword']);
    // Legacy profile aliases the client still calls.
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user/update', [AuthController::class, 'updateMe']);
    Route::post('/uploads', [SupportController::class, 'upload']);

    // Cart
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'add']);
    Route::patch('/cart/items/{book}', [CartController::class, 'update']);
    Route::delete('/cart/items/{book}', [CartController::class, 'remove']);
    Route::delete('/cart', [CartController::class, 'clear']);
    // Legacy cart aliases (per-cart-item id based).
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::put('/cart/update/{cartItem}', [CartController::class, 'updateByItem']);
    Route::delete('/cart/remove/{cartItem}', [CartController::class, 'removeByItem']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{book}', [WishlistController::class, 'add'])->whereNumber('book');
    Route::delete('/wishlist/{book}', [WishlistController::class, 'remove'])->whereNumber('book');
    Route::delete('/wishlist', [WishlistController::class, 'clear']);
    // Toggle alias used by the client book-details screen.
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    // Checkout alias — places the order from the cart itself.
    Route::post('/orders/checkout', [OrderController::class, 'checkout']);

    // Reviews
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::post('/reviews/{review}/like', [ReviewController::class, 'toggleLike']);

    // Addresses & payment methods
    Route::get('/addresses', [AccountController::class, 'addresses']);
    Route::post('/addresses', [AccountController::class, 'storeAddress']);
    Route::put('/addresses/{address}', [AccountController::class, 'updateAddress']);
    Route::delete('/addresses/{address}', [AccountController::class, 'destroyAddress']);
    Route::get('/payment-methods', [AccountController::class, 'paymentMethods']);
    Route::post('/payment-methods', [AccountController::class, 'storePaymentMethod']);
    Route::put('/payment-methods/{method}', [AccountController::class, 'updatePaymentMethod']);
    Route::delete('/payment-methods/{method}', [AccountController::class, 'destroyPaymentMethod']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    // Support
    Route::post('/support/messages', [SupportController::class, 'sendMessage']);
    // ── Admin ──────────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {
        Route::get('/admin/stats', [AdminController::class, 'stats']);
        Route::get('/admin/books', [AdminController::class, 'books']);
        Route::post('/admin/books', [AdminController::class, 'storeBook']);
        Route::put('/admin/books/{book}', [AdminController::class, 'updateBook']);
        Route::delete('/admin/books/{book}', [AdminController::class, 'destroyBook']);
        Route::get('/admin/categories', [AdminController::class, 'categories']);
        Route::post('/admin/categories', [AdminController::class, 'storeCategory']);
        Route::put('/admin/categories/{category}', [AdminController::class, 'updateCategory']);
        Route::delete('/admin/categories/{category}', [AdminController::class, 'destroyCategory']);
        Route::get('/admin/authors', [AdminController::class, 'authors']);
        Route::post('/admin/authors', [AdminController::class, 'storeAuthor']);
        Route::put('/admin/authors/{author}', [AdminController::class, 'updateAuthor']);
        Route::delete('/admin/authors/{author}', [AdminController::class, 'destroyAuthor']);
        Route::get('/admin/orders', [AdminController::class, 'orders']);
        Route::patch('/admin/orders/{order}/status', [AdminController::class, 'updateOrderStatus']);
        Route::get('/admin/users', [AdminController::class, 'users']);
        Route::patch('/admin/users/{user}/block', [AdminController::class, 'blockUser']);
    });

    // The review flow refreshes the denormalised rating itself, so this one
    // endpoint only needs a valid session — not the admin role. The client
    // PATCHes `/admin/books/{id}` directly (never the `/rating` suffix).
    Route::patch('/admin/books/{book}', [AdminController::class, 'updateBookRating']);
});
