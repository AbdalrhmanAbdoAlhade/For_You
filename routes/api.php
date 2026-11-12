<?php

use Illuminate\Support\Facades\Route;

// ===== Public / Customer (Api) =====
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\CategoryController as ApiCategoryController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\StoreInfluencerController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\NotificationController;

// ===== Influencer area =====
use App\Http\Controllers\Api\Influencer\DashboardController as InfluencerDashboardController;
use App\Http\Controllers\Api\Influencer\WalletController as InfluencerWalletController;
use App\Http\Controllers\Api\Influencer\ProductController as InfluencerProductController;
use App\Http\Controllers\Api\Influencer\LinkController as InfluencerLinkController;
use App\Http\Controllers\Api\Influencer\SalesController as InfluencerSalesApiController;

// ===== Admin / Employees =====
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\InfluencerController;
use App\Http\Controllers\Admin\PromotionalLinkController;
use App\Http\Controllers\Admin\InfluencerWalletController as AdminInfluencerWalletController;
use App\Http\Controllers\Admin\InfluencerSalesController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Admin\InfluencerProductController as AdminInfluencerProductController; 

// ======================================================================
// Public Routes
// ======================================================================

// ---- Auth ----
// كل route له limiter خاص بيه (متعرّفين في AppServiceProvider)
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password');
Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:reset-password');

// ---- Social Login ----
Route::prefix('auth')->middleware('throttle:social')->group(function () {
    Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('{provider}/callback', [SocialAuthController::class, 'callback']);
    Route::post('{provider}/token', [SocialAuthController::class, 'loginWithToken']);
});

// ---- Store ----
Route::get('home', [HomeController::class, 'index']);

Route::get('categories', [ApiCategoryController::class, 'index']);
Route::get('categories/{id}', [ApiCategoryController::class, 'show']);

// الترتيب مهم: الـ routes الثابتة (featured, offers, new) لازم قبل products/{id}
Route::get('products', [ApiProductController::class, 'index']);
Route::get('products/featured', [ApiProductController::class, 'featured']);
Route::get('products/offers', [HomeController::class, 'offers']);
Route::get('products/new', [HomeController::class, 'newArrivals']);
Route::get('products/{id}', [ApiProductController::class, 'show']);

Route::get('influencers', [StoreInfluencerController::class, 'index']);
Route::get('influencers/{id}/products', [StoreInfluencerController::class, 'products']);

// ---- تتبع الرابط الترويجي (بيزود الـ clicks) ----
Route::get('track/{code}', [TrackController::class, 'show'])->middleware('throttle:api');

// ======================================================================
// Protected Routes
// ======================================================================
Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {

     // ---- Account ----
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::match(['put', 'patch'], 'profile', [AuthController::class, 'updateProfile']);
        // throttle:auth عشان محدش يخمّن الباسورد الحالي بتوكن مسروق
              // throttle:change-password عشان محدش يخمّن الباسورد الحالي بتوكن مسروق
        Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:change-password');
    });

    // ---- Addresses ----
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::match(['put', 'patch'], '{id}', [AddressController::class, 'update']);
        Route::patch('{id}/default', [AddressController::class, 'setDefault']);
        Route::delete('{id}', [AddressController::class, 'destroy']);
    });

    // ---- Orders (Customer) ---- (ضيف cancel)
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/', [OrderController::class, 'myOrders']);
        Route::post('{id}/cancel', [OrderController::class, 'cancel']);
        Route::get('{id}', [OrderController::class, 'show']);
    });
    // ---- Cart ----
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::put('/{itemId}', [CartController::class, 'update']);
        Route::delete('/{itemId}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
    });

    // ---- Checkout ----
    Route::prefix('checkout')->group(function () {
        Route::post('quote', [CheckoutController::class, 'quote']);
        Route::post('/', [CheckoutController::class, 'store']);
    });

 

    // ---- Notifications ----
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // ==================================================================
    // Influencer Area (My Products / My Sales / My Percentage / My Wallet)
    // ==================================================================
    Route::prefix('influencer')->middleware('influencer')->group(function () {
        Route::get('dashboard', [InfluencerDashboardController::class, 'index']);
        Route::get('products', [InfluencerProductController::class, 'index']);
        Route::get('percentage', [InfluencerProductController::class, 'percentage']);
        Route::get('links', [InfluencerLinkController::class, 'index']);
        Route::get('sales', [InfluencerSalesApiController::class, 'index']);

        Route::get('wallet', [InfluencerWalletController::class, 'show']);
        Route::post('wallet/withdraw', [InfluencerWalletController::class, 'requestWithdrawal']);
        Route::get('wallet/withdrawals', [InfluencerWalletController::class, 'myWithdrawals']);
    });

    // ==================================================================
    // Admin / Employees (كل route له صلاحية)
    // ==================================================================
    Route::prefix('admin')->middleware('admin')->group(function () {

        // ---- Dashboard ----
        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:dashboard.view');

        // ---- Categories ----
        Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
        Route::get('categories/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:categories.create');
        Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.edit');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

        // ---- Products ----
        Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create');
        Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

        // ---- Orders ----
        Route::get('orders', [AdminOrderController::class, 'index'])->middleware('permission:orders.view,orders.manage');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->middleware('permission:orders.view,orders.manage');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->middleware('permission:orders.update,orders.manage,orders.cancel');
        Route::post('orders/{order}/shipping', [ShippingController::class, 'store'])->middleware('permission:shipping.manage');

        // ---- Customers ----
        Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view,customers.manage');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view,customers.manage');
        Route::patch('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->middleware('permission:customers.manage');

        // ---- Payments ----
        Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.view,payments.manage');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:payments.view,payments.manage');
               Route::patch('payments/{payment}/status', [PaymentController::class, 'updateStatus'])->middleware('permission:payments.confirm,payments.refund');

        // ---- Shipping ----
        Route::get('shipping', [ShippingController::class, 'index'])->middleware('permission:shipping.view,shipping.manage');
        Route::get('shipping/{shipping}', [ShippingController::class, 'show'])->middleware('permission:shipping.view,shipping.manage');
        Route::match(['put', 'patch'], 'shipping/{shipping}', [ShippingController::class, 'update'])->middleware('permission:shipping.manage');
        Route::patch('shipping/{shipping}/status', [ShippingController::class, 'updateStatus'])->middleware('permission:shipping.manage');

                    // ---- Influencer ↔ Product (منتج واحد) ----
        Route::get('products/{product}/influencers', [AdminInfluencerProductController::class, 'forProduct'])->middleware('permission:influencers.products');
        Route::post('products/{product}/influencers', [AdminInfluencerProductController::class, 'attachFromProduct'])->middleware('permission:influencers.products');
        Route::post('influencers/{influencer}/products/{product}', [AdminInfluencerProductController::class, 'attach'])->middleware('permission:influencers.products');
        Route::patch('influencers/{influencer}/products/{product}', [AdminInfluencerProductController::class, 'update'])->middleware('permission:influencers.products');
        Route::delete('influencers/{influencer}/products/{product}', [AdminInfluencerProductController::class, 'detach'])->middleware('permission:influencers.products');
        // ---- Influencers ----
        Route::get('influencers', [InfluencerController::class, 'index'])->middleware('permission:influencers.view');
        Route::get('influencers/{influencer}', [InfluencerController::class, 'show'])->middleware('permission:influencers.view');
        Route::post('influencers', [InfluencerController::class, 'store'])->middleware('permission:influencers.create');
        Route::match(['put', 'patch'], 'influencers/{influencer}', [InfluencerController::class, 'update'])->middleware('permission:influencers.edit');
        Route::delete('influencers/{influencer}', [InfluencerController::class, 'destroy'])->middleware('permission:influencers.delete');
        Route::post('influencers/{influencer}/products', [InfluencerController::class, 'attachProducts'])->middleware('permission:influencers.products');
        Route::post('influencers/{influencer}/links', [InfluencerController::class, 'generateLink'])->middleware('permission:influencers.links');
        Route::get('influencers/{influencer}/sales', [InfluencerSalesController::class, 'forInfluencer'])->middleware('permission:influencers.view,influencers.commissions');

        // ---- Influencer Sales ----
        Route::get('influencer-sales', [InfluencerSalesController::class, 'index'])->middleware('permission:influencers.view,influencers.commissions');

        // ---- Promotional Links ----
        Route::get('links', [PromotionalLinkController::class, 'index'])->middleware('permission:influencers.links');
        Route::get('links/{link}', [PromotionalLinkController::class, 'show'])->middleware('permission:influencers.links');
        Route::match(['put', 'patch'], 'links/{link}', [PromotionalLinkController::class, 'update'])->middleware('permission:influencers.links');
        Route::patch('links/{link}/toggle', [PromotionalLinkController::class, 'toggle'])->middleware('permission:influencers.links');

        // ---- Influencer Wallets & Withdrawals ----
        Route::get('wallets', [AdminInfluencerWalletController::class, 'index'])->middleware('permission:influencers.wallets');
        Route::get('wallets/{wallet}', [AdminInfluencerWalletController::class, 'show'])->middleware('permission:influencers.wallets');
        Route::get('wallet-transactions', [AdminInfluencerWalletController::class, 'transactions'])->middleware('permission:influencers.wallets');
              Route::get('withdrawals', [WithdrawalController::class, 'index'])->middleware('permission:withdrawals.view,withdrawals.approve,withdrawals.pay');
        Route::patch('withdrawals/{withdrawal}/process', [WithdrawalController::class, 'process'])
            ->middleware('permission:withdrawals.approve,withdrawals.pay');
    ->middleware('permission:influencers.wallets');

        // ---- Banners ----
        Route::get('banners', [BannerController::class, 'index'])->middleware('permission:banners.view');
        Route::get('banners/{banner}', [BannerController::class, 'show'])->middleware('permission:banners.view');
        Route::post('banners', [BannerController::class, 'store'])->middleware('permission:banners.create');
        Route::match(['put', 'patch'], 'banners/{banner}', [BannerController::class, 'update'])->middleware('permission:banners.edit');
        Route::delete('banners/{banner}', [BannerController::class, 'destroy'])->middleware('permission:banners.delete');

        // ---- Coupons ----
        Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:coupons.view');
        Route::get('coupons/{coupon}', [CouponController::class, 'show'])->middleware('permission:coupons.view');
        Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:coupons.create');
        Route::match(['put', 'patch'], 'coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:coupons.edit');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:coupons.delete');

        // ---- Reports ----
        Route::get('reports/overview', [ReportController::class, 'overview'])->middleware('permission:reports.view');
        Route::get('reports/sales', [ReportController::class, 'sales'])->middleware('permission:reports.sales,reports.view');
        Route::get('reports/influencers', [ReportController::class, 'influencers'])->middleware('permission:reports.influencers,reports.view');
        Route::get('reports/top-products', [ReportController::class, 'topProducts'])->middleware('permission:reports.view');

        // ---- Roles & Permissions ----
        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.manage,permissions.manage');
        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.manage');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
        Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.manage');
        Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage');

        // ---- Employees ----
        Route::get('employees', [EmployeeController::class, 'index'])->middleware('permission:employees.view');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('permission:employees.view');
        Route::post('employees', [EmployeeController::class, 'store'])->middleware('permission:employees.create');
        Route::match(['put', 'patch'], 'employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:employees.edit');
        Route::patch('employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->middleware('permission:employees.edit');
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:employees.delete');
    });
});
