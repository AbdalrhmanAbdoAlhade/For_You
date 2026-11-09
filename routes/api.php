<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Influencer\DashboardController as InfluencerDashboardController;
use App\Http\Controllers\Api\Influencer\WalletController as InfluencerWalletController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\InfluencerController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\EmployeeController; // هنعمله لو حابب
use App\Http\Controllers\Api\CategoryController as ApiCategoryController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Api\SocialAuthController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
// Social Login
Route::prefix('auth')->group(function () {

    // للويب (Redirect + Callback)
    Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('{provider}/callback', [SocialAuthController::class, 'callback']);

    // للموبايل (بيستقبل التوكن من SDK)
    Route::post('{provider}/token', [SocialAuthController::class, 'loginWithToken']);
});

// ========================
// Public Auth Routes
// ========================
Route::middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::get('categories', [ApiCategoryController::class, 'index']);
Route::get('categories/{id}', [ApiCategoryController::class, 'show']);

Route::get('products', [ApiProductController::class, 'index']);
Route::get('products/featured', [ApiProductController::class, 'featured']);
Route::get('products/{id}', [ApiProductController::class, 'show']);

// ========================
// Protected Routes (يحتاج توكن)
// ========================
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Cart
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/', [CartController::class, 'store']);
    Route::put('/{itemId}', [CartController::class, 'update']);
    Route::delete('/{itemId}', [CartController::class, 'destroy']);
    Route::delete('/', [CartController::class, 'clear']);
});

// Notifications
Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread', [NotificationController::class, 'unread']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
});
    // ========================
    // Customer Routes
    // ========================
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/', [OrderController::class, 'myOrders']);
        Route::get('{id}', [OrderController::class, 'show']);
    });

    // ========================
    // Influencer Routes
    // ========================
    Route::prefix('influencer')->middleware('influencer')->group(function () {
        Route::get('dashboard', [InfluencerDashboardController::class, 'index']);
        Route::get('wallet', [InfluencerWalletController::class, 'show']);
        Route::post('wallet/withdraw', [InfluencerWalletController::class, 'requestWithdrawal']);
    Route::get('wallet/withdrawals', [InfluencerWalletController::class, 'myWithdrawals']);
    });

    // ========================
    // Admin Routes
    // ========================
    Route::prefix('admin')->middleware('admin')->group(function () {

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Categories
        Route::apiResource('categories', CategoryController::class);

        // Products
        Route::apiResource('products', ProductController::class);

        // Orders
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{order}', [AdminOrderController::class, 'show']);
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
        Route::get('withdrawals', [WithdrawalController::class, 'index']);
        Route::patch('withdrawals/{withdrawal}/process', [WithdrawalController::class, 'process']);

    // Reports
    Route::get('reports/overview', [ReportController::class, 'overview']);
    Route::get('reports/sales', [ReportController::class, 'sales']);
    Route::get('reports/influencers', [ReportController::class, 'influencers']);
    Route::get('reports/top-products', [ReportController::class, 'topProducts']);
        // Influencers
        Route::apiResource('influencers', InfluencerController::class);
        Route::post('influencers/{influencer}/products', [InfluencerController::class, 'attachProducts']);
        Route::post('influencers/{influencer}/links', [InfluencerController::class, 'generateLink']);

        // Roles & Permissions
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', [RoleController::class, 'permissions']);

     // Employees
        Route::apiResource('employees', EmployeeController::class);
        Route::patch('employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus']);
    });
});
