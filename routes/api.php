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
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ShippingWebhookController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\CreatorProfileController;

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

use App\Http\Controllers\Api\BrandController as ApiBrandController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\Influencer\StoryController as InfluencerStoryController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\InfluencerStoryController as AdminInfluencerStoryController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\SupportController;


// ======================================================================
// Public Routes
// ======================================================================

Route::get('faqs', [FaqController::class, 'index']);
Route::get('support/contact', [SupportController::class, 'contact']);

Route::get('categories/{id}/brands', [ApiCategoryController::class, 'brands']);
Route::get('influencers/{id}', [CreatorProfileController::class, 'show']);
Route::get('influencers/{id}/picks', [CreatorProfileController::class, 'picks']);
// ---- Auth ----
// كل route له limiter خاص بيه (متعرّفين في AppServiceProvider)
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password');
Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:reset-password');
Route::post('auth/forgot-password-otp', [AuthController::class, 'forgotPasswordOtp'])->middleware('throttle:3,1');
Route::post('auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
Route::post('auth/reset-password-otp', [AuthController::class, 'resetPasswordOtp'])->middleware('throttle:3,1');

// ---- Social Login ----
Route::prefix('auth')->middleware('throttle:social')->group(function () {
    Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('{provider}/callback', [SocialAuthController::class, 'callback']);
    Route::post('{provider}/token', [SocialAuthController::class, 'loginWithToken']);
});


// ==================== Reviews (Public) ====================
Route::get('products/{product}/reviews', [ReviewController::class, 'productIndex']);
Route::get('products/{product}/reviews/summary', [ReviewController::class, 'productSummary']);
Route::get('products/{product}/related', [ApiProductController::class, 'related']);

Route::get('brands/{brand}/reviews', [ReviewController::class, 'brandIndex']);
Route::get('brands/{brand}/reviews/summary', [ReviewController::class, 'brandSummary']);

Route::get('influencers/{user}/reviews', [ReviewController::class, 'userIndex']);

// ---- Search ----
Route::prefix('search')->group(function () {
    Route::get('/', [SearchController::class, 'unified']);
    Route::get('products', [SearchController::class, 'products']);
    Route::get('brands', [SearchController::class, 'brands']);
    Route::get('creators', [SearchController::class, 'creators']);
    Route::get('trending', [SearchController::class, 'trending']);
    Route::get('recent', [SearchController::class, 'recent'])->middleware('auth:sanctum');
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
Route::get('products/trending', [RecommendationController::class, 'trending']);
Route::get('products/{product}', [ApiProductController::class, 'show']);
Route::get('products/{product}/variants', [ApiProductController::class, 'variants']);

Route::get('influencers', [StoreInfluencerController::class, 'index']);
Route::get('influencers/{id}/products', [StoreInfluencerController::class, 'products']);
Route::get('influencers/{id}/stories', [StoryController::class, 'creator']);

Route::get('stories/discover', [StoryController::class, 'discover']);
Route::get('stories/featured', [StoryController::class, 'featured']);

Route::get('brands', [ApiBrandController::class, 'index']);
Route::get('brands/{id}', [ApiBrandController::class, 'show']);

// ---- تتبع الرابط الترويجي (بيزود الـ clicks) ----
Route::get('track/{code}', [TrackController::class, 'show'])->middleware('throttle:track');

// ---- Webhooks (بدون Sanctum: التوقيع هو وسيلة التحقق) ----
Route::post('webhooks/payments/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->middleware('throttle:webhook');
Route::post('webhooks/shipping/{gateway}', [ShippingWebhookController::class, 'handle'])
    ->middleware('throttle:webhook');

// ======================================================================
// Protected Routes
// ======================================================================
Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {

  Route::patch('language', [AuthController::class, 'updateLanguage']);
  	// ---- Devices (FCM) ----
Route::prefix('devices')->group(function () {
    Route::get('/', [DeviceController::class, 'index']);
    Route::post('/', [DeviceController::class, 'store']);
    Route::delete('/', [DeviceController::class, 'destroy']);
});
     // ---- Account ----
Route::prefix('auth')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::match(['post', 'put', 'patch'], 'profile', [AuthController::class, 'updateProfile']);
    Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:change-password');
    Route::patch('language', [AuthController::class, 'updateLanguage']);  // ← جديد
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
        Route::post('{id}/pay', [OrderController::class, 'pay']);
        Route::get('{id}', [OrderController::class, 'show']);
      Route::get('{id}/tracking', [OrderController::class, 'tracking']);
       Route::post('{id}/buy-again', [OrderController::class, 'buyAgain']);
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

            // ---- Stories (مشاهدة/لايك) ----
        Route::get('stories/rail', [StoryController::class, 'rail']);
        Route::post('stories/{id}/view', [StoryController::class, 'view']);
        Route::post('stories/{id}/like', [StoryController::class, 'like']);
        Route::delete('stories/{id}/like', [StoryController::class, 'unlike']);

        // ---- Follow ----
        Route::post('influencers/{id}/follow', [FollowController::class, 'follow']);
        Route::delete('influencers/{id}/follow', [FollowController::class, 'unfollow']);
        Route::get('following', [FollowController::class, 'following']);

        // ---- Wishlist / Likes ----
        Route::get('wishlist', [WishlistController::class, 'index']);
        Route::get('wishlist/ids', [WishlistController::class, 'ids']);
        Route::post('products/{id}/like', [WishlistController::class, 'like']);
        Route::delete('products/{id}/like', [WishlistController::class, 'unlike']);
          // ---- Reviews (كتابة) ----
        Route::get('products/{product}/review-state', [ReviewController::class, 'state']);
        Route::post('products/{product}/reviews', [ReviewController::class, 'store']);
        Route::match(['put', 'patch'], 'reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);
  
    // ==================================================================
    // Influencer Area (My Products / My Sales / My Percentage / My Wallet)
    // ==================================================================
    Route::prefix('influencer')->middleware('influencer')->group(function () {
        Route::get('dashboard', [InfluencerDashboardController::class, 'index']);
        Route::get('products', [InfluencerProductController::class, 'index']);
        Route::get('percentage', [InfluencerProductController::class, 'percentage']);
        Route::get('links', [InfluencerLinkController::class, 'index']);
        Route::get('sales', [InfluencerSalesApiController::class, 'index']);
Route::post('links', [InfluencerLinkController::class, 'store'])->middleware('throttle:20,1');
      
Route::get('sales/by-product', [InfluencerSalesApiController::class, 'byProduct']);

        Route::get('wallet', [InfluencerWalletController::class, 'show']);
        Route::post('wallet/withdraw', [InfluencerWalletController::class, 'requestWithdrawal']);
        Route::get('wallet/withdrawals', [InfluencerWalletController::class, 'myWithdrawals']);
      
              Route::get('stories', [InfluencerStoryController::class, 'index']);
        Route::post('stories', [InfluencerStoryController::class, 'store'])->middleware('throttle:20,1');
        Route::patch('stories/{id}', [InfluencerStoryController::class, 'update']);
        Route::delete('stories/{id}', [InfluencerStoryController::class, 'destroy']);
    });

    // ==================================================================
    // Admin / Employees (كل route له صلاحية)
    // ==================================================================
    Route::prefix('admin')->middleware('admin')->group(function () {

      Route::get('reports/customers', [ReportController::class, 'customers'])
    ->middleware('permission:reports.view');
Route::get('reports/categories', [ReportController::class, 'categories'])
    ->middleware('permission:reports.view');
      	// ---- Reviews ----
        Route::get('reviews', [AdminReviewController::class, 'index'])->middleware('permission:reviews.manage');
        Route::patch('reviews/{review}/active', [AdminReviewController::class, 'setActive'])->middleware('permission:reviews.manage');
        Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy'])->middleware('permission:reviews.manage');
              // ---- Brands ----
        Route::get('brands', [AdminBrandController::class, 'index'])->middleware('permission:brands.view');
        Route::get('brands/{brand}', [AdminBrandController::class, 'show'])->middleware('permission:brands.view');
        Route::post('brands', [AdminBrandController::class, 'store'])->middleware('permission:brands.create');
        Route::match(['put', 'post'], 'brands/{brand}', [AdminBrandController::class, 'update'])->middleware('permission:brands.edit');
        Route::delete('brands/{brand}', [AdminBrandController::class, 'destroy'])->middleware('permission:brands.delete');

        // ---- Influencer Stories (إخفاء/حذف محتوى مخالف) ----
        Route::get('stories', [AdminInfluencerStoryController::class, 'index'])->middleware('permission:influencer_stories.manage');
        Route::patch('stories/{story}/status', [AdminInfluencerStoryController::class, 'updateStatus'])->middleware('permission:influencer_stories.manage');
        Route::delete('stories/{story}', [AdminInfluencerStoryController::class, 'destroy'])->middleware('permission:influencer_stories.manage');
      
        // ---- Dashboard ----
        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:dashboard.view');

        // ---- Categories ----
        Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
        Route::get('categories/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:categories.create');
        Route::match(['put', 'post'], 'categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.edit');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

        // ---- Products ----
        Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create');
        Route::match(['put', 'post'], 'products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');
      
              // ---- Product Variants ----
        Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])
            ->middleware('permission:products.view');
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])
            ->middleware('permission:products.edit');
        Route::put('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])
            ->middleware('permission:products.edit');
        Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])
            ->middleware('permission:products.edit');
        Route::post('products/{product}/convert-to-variants', [ProductVariantController::class, 'convert'])
            ->middleware('permission:products.edit');

        // ---- Orders ----
        Route::get('orders', [AdminOrderController::class, 'index'])->middleware('permission:orders.view,orders.manage');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->middleware('permission:orders.view,orders.manage');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->middleware('permission:orders.update,orders.manage,orders.cancel');
        Route::post('orders/{order}/shipping', [ShippingController::class, 'store'])->middleware('permission:shipping.manage');
        Route::get('orders/{order}/tracking', [AdminOrderController::class, 'tracking'])->middleware('permission:orders.view,orders.manage');
        // ---- Customers ----
        Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view,customers.manage');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view,customers.manage');
        Route::match(['post', 'put', 'patch'], 'customers/{customer}', [CustomerController::class, 'update'])
    ->middleware('permission:customers.manage');
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
        Route::match(['put', 'post'], 'coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:coupons.edit');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:coupons.delete');

      		Route::post('coupons/{coupon}/broadcast', [CouponController::class, 'broadcast'])
    ->middleware('permission:coupons.edit');
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
