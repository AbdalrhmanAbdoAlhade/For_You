<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\CategoryController as ApiCategoryController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\Api\Influencer\DashboardController as InfluencerDashboardController;
use App\Http\Controllers\Api\Influencer\WalletController as InfluencerWalletController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\InfluencerController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Admin\ReportController;

// ========================
// Social Login
// ========================
Route::prefix('auth')->group(function () {
    Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('{provider}/callback', [SocialAuthController::class, 'callback']);
    Route::post('{provider}/token', [SocialAuthController::class, 'loginWithToken']);
});

// ========================
// Public Routes
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

// تتبع الرابط الترويجي (بيزود الـ clicks)
Route::get('track/{code}', [TrackController::class, 'show'])->middleware('throttle:api');

// ========================
// Protected Routes
// ========================
Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::put('/{itemId}', [CartController::class, 'update']);
        Route::delete('/{itemId}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // ===== Customer =====
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/', [OrderController::class, 'myOrders']);
        Route::get('{id}', [OrderController::class, 'show']);
    });

    // ===== Influencer =====
    Route::prefix('influencer')->middleware('influencer')->group(function () {
        Route::get('dashboard', [InfluencerDashboardController::class, 'index']);
        Route::get('wallet', [InfluencerWalletController::class, 'show']);
        Route::post('wallet/withdraw', [InfluencerWalletController::class, 'requestWithdrawal']);
        Route::get('wallet/withdrawals', [InfluencerWalletController::class, 'myWithdrawals']);
    });

    // ===== Admin / Employees (كل route له صلاحية) =====
    Route::prefix('admin')->middleware('admin')->group(function () {

        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:dashboard.view');

        // Categories
        Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
        Route::get('categories/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:categories.create');
        Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.edit');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

        // Products
        Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create');
        Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

        // Orders
        Route::get('orders', [AdminOrderController::class, 'index'])->middleware('permission:orders.view,orders.manage');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->middleware('permission:orders.view,orders.manage');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->middleware('permission:orders.update,orders.manage,orders.cancel');

        // Withdrawals
        Route::get('withdrawals', [WithdrawalController::class, 'index'])->middleware('permission:influencers.wallets');
        Route::patch('withdrawals/{withdrawal}/process', [WithdrawalController::class, 'process'])->middleware('permission:influencers.commissions');

        // Reports
        Route::get('reports/overview', [ReportController::class, 'overview'])->middleware('permission:reports.view');
        Route::get('reports/sales', [ReportController::class, 'sales'])->middleware('permission:reports.sales,reports.view');
        Route::get('reports/influencers', [ReportController::class, 'influencers'])->middleware('permission:reports.influencers,reports.view');
        Route::get('reports/top-products', [ReportController::class, 'topProducts'])->middleware('permission:reports.view');

        // Influencers
        Route::get('influencers', [InfluencerController::class, 'index'])->middleware('permission:influencers.view');
        Route::get('influencers/{influencer}', [InfluencerController::class, 'show'])->middleware('permission:influencers.view');
        Route::post('influencers', [InfluencerController::class, 'store'])->middleware('permission:influencers.create');
        Route::match(['put', 'patch'], 'influencers/{influencer}', [InfluencerController::class, 'update'])->middleware('permission:influencers.edit');
        Route::delete('influencers/{influencer}', [InfluencerController::class, 'destroy'])->middleware('permission:influencers.delete');
        Route::post('influencers/{influencer}/products', [InfluencerController::class, 'attachProducts'])->middleware('permission:influencers.products');
        Route::post('influencers/{influencer}/links', [InfluencerController::class, 'generateLink'])->middleware('permission:influencers.links');

        // Roles & Permissions
        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.manage,permissions.manage');
        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.manage');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
        Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.manage');
        Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage');

        // Employees
        Route::get('employees', [EmployeeController::class, 'index'])->middleware('permission:employees.view');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('permission:employees.view');
        Route::post('employees', [EmployeeController::class, 'store'])->middleware('permission:employees.create');
        Route::match(['put', 'patch'], 'employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:employees.edit');
        Route::patch('employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->middleware('permission:employees.edit');
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:employees.delete');
    });
});
