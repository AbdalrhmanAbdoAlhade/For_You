<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\PromotionalLinkService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\InfluencerCommission;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use App\Services\FileUploadService;

class InfluencerController extends Controller
{
    public function __construct(
    protected PromotionalLinkService $linkService,
    protected WalletService $walletService,
    protected FileUploadService $fileUploadService
) {}

  public function index(Request $request)
{
    $request->validate([
        'search'    => 'nullable|string|max:100',
        'is_active' => 'nullable|boolean',
    ]);

    $since30d = now()->subDays(30);

    $influencers = User::influencers()
        ->with('wallet')
        ->addSelect([
            'sales_links_30d_orders' => Order::selectRaw('COUNT(*)')
                ->whereColumn('influencer_id', 'users.id')
                ->whereIn('status', Order::SALE_STATUSES)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $since30d),

            'sales_links_30d_revenue' => Order::selectRaw('COALESCE(SUM(total), 0)')
                ->whereColumn('influencer_id', 'users.id')
                ->whereIn('status', Order::SALE_STATUSES)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $since30d),

            'commission_earned_total' => InfluencerCommission::selectRaw('COALESCE(SUM(commission_amount), 0)')
                ->whereColumn('influencer_id', 'users.id')
                ->whereIn('status', InfluencerCommission::EARNED_STATUSES),

            'commission_pending_total' => InfluencerCommission::selectRaw('COALESCE(SUM(commission_amount), 0)')
                ->whereColumn('influencer_id', 'users.id')
                ->where('status', 'pending'),

            'avg_commission_rate' => DB::table('influencer_product')
                ->selectRaw('COALESCE(AVG(commission_percentage), 0)')
                ->whereColumn('influencer_id', 'users.id')
                ->where('is_active', true)
                ->whereNotNull('commission_percentage'),
        ])
        ->when($request->search, function ($q) use ($request) {
            $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        })
        ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
        ->latest()
        ->paginate(20);

    $influencers->getCollection()->transform(function ($inf) {
        $inf->setAttribute('sales_through_links_30d', [
            'orders_count' => (int) ($inf->sales_links_30d_orders ?? 0),
            'revenue'      => (float) ($inf->sales_links_30d_revenue ?? 0),
        ]);
        $inf->setAttribute('commission_earned', (float) ($inf->commission_earned_total ?? 0));
        $inf->setAttribute('commission_pending', (float) ($inf->commission_pending_total ?? 0));
        $inf->setAttribute('wallet_balance_owed', (float) ($inf->wallet?->balance ?? 0));
        $inf->setAttribute('avg_commission_rate', round((float) ($inf->avg_commission_rate ?? 0), 2));

        $inf->makeHidden([
            'sales_links_30d_orders', 'sales_links_30d_revenue',
            'commission_earned_total', 'commission_pending_total',
        ]);

        return $inf;
    });

    $payload = $influencers->toArray();
    $payload['stats'] = $this->stats($since30d);

    return response()->json($payload);
}

/**
 * إحصائيات كروت صفحة المؤثرين (إجمالية لكل المؤثرين، مش متأثرة بالبحث/الفلتر).
 */
protected function stats($since30d): array
{
    // Sales through links · 30 days
    $sales = Order::whereNotNull('influencer_id')
        ->whereIn('status', Order::SALE_STATUSES)
        ->where('created_at', '>=', $since30d)
        ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as revenue')
        ->first();

    // Commission earned
    $commissionEarned = (float) InfluencerCommission::whereIn('status', InfluencerCommission::EARNED_STATUSES)
        ->sum('commission_amount');

    // Wallet balance owed (مجموع أرصدة محافظ كل المؤثرين)
    $walletOwed = (float) User::influencers()
        ->withSum('wallet as wallet_total', 'balance')
        ->get()
        ->sum('wallet_total');

    // Avg. commission rate (على الربط النشط بنسبة محددة)
    $avgRate = (float) DB::table('influencer_product')
        ->where('is_active', true)
        ->whereNotNull('commission_percentage')
        ->avg('commission_percentage');

    return [
        'sales_through_links_30d' => [
            'orders_count' => (int) ($sales->orders_count ?? 0),
            'revenue'      => (float) ($sales->revenue ?? 0),
        ],
        'commission_earned'   => round($commissionEarned, 2),
        'wallet_balance_owed' => round($walletOwed, 2),
        'avg_commission_rate' => round($avgRate, 2),
    ];
}
    public function store(Request $request)
{
    $data = $request->validate([
        'name'      => 'required|string|max:255',
        'email'     => 'required|email|unique:users,email',
        'phone'     => 'nullable|string|unique:users,phone',
        'password'  => 'required|string|min:8',
        'is_active' => 'boolean',
        'avatar'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    $avatar = null;

    try {
        if ($request->hasFile('avatar')) {
            $avatar = $this->fileUploadService->upload($request->file('avatar'), 'avatars');
            $data['avatar'] = $avatar;
        } else {
            unset($data['avatar']);
        }

        $influencer = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'type'     => 'influencer',
        ]);
    } catch (\Throwable $e) {
        $this->fileUploadService->delete($avatar);

        throw $e;
    }

    // إنشاء محفظة تلقائيًا
    $this->walletService->getOrCreateWallet($influencer->id);

    AuditLog::record('influencer.created', $influencer, ['email' => $influencer->email]);

    return response()->json($influencer, 201);
}

public function show(User $influencer)
{
    abort_if($influencer->type !== 'influencer', 404);

    $influencer->load(['wallet', 'influencerProducts', 'promotionalLinks', 'commissions']);

    // مبيعات الطلبات عبر روابط المؤثر (كل الفترة)
    $allTime = Order::where('influencer_id', $influencer->id)
        ->whereIn('status', Order::SALE_STATUSES)
        ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as revenue')
        ->first();

    // آخر 30 يوم
    $last30 = Order::where('influencer_id', $influencer->id)
        ->whereIn('status', Order::SALE_STATUSES)
        ->where('created_at', '>=', now()->subDays(30))
        ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as revenue')
        ->first();

    $payload = $influencer->toArray();

    $payload['sales_through_links'] = [
        'orders_count' => (int) ($allTime->orders_count ?? 0),
        'revenue'      => round((float) ($allTime->revenue ?? 0), 2),
    ];

    $payload['sales_through_links_30d'] = [
        'orders_count' => (int) ($last30->orders_count ?? 0),
        'revenue'      => round((float) ($last30->revenue ?? 0), 2),
    ];

    return response()->json($payload);
}

   public function update(Request $request, User $influencer)
{
    abort_if($influencer->type !== 'influencer', 404);

    $data = $request->validate([
        'name'      => 'sometimes|string|max:255',
        'email'     => 'sometimes|email|unique:users,email,' . $influencer->id,
        'phone'     => 'nullable|string|unique:users,phone,' . $influencer->id,
        'password'  => 'nullable|string|min:8',
        'is_active' => 'boolean',
        'avatar'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    if (!empty($data['password'])) {
        $data['password'] = Hash::make($data['password']);
    } else {
        unset($data['password']);
    }

    $oldAvatar = $influencer->avatar;
    $newAvatar = null;

    if ($request->hasFile('avatar')) {
        $newAvatar = $this->fileUploadService->upload($request->file('avatar'), 'avatars');
        $data['avatar'] = $newAvatar;
    } else {
        unset($data['avatar']);
    }

    try {
        $influencer->update($data);
    } catch (\Throwable $e) {
        $this->fileUploadService->delete($newAvatar);

        throw $e;
    }

    if ($newAvatar) {
        $this->fileUploadService->delete($oldAvatar);
    }

    AuditLog::record('influencer.updated', $influencer, [
        'email'     => $influencer->email,
        'is_active' => $influencer->is_active,
    ]);

    // تفعيل/تعطيل المؤثر بيغيّر influencer_picks في الرئيسية
    HomeController::flush();

    return response()->json($influencer->fresh());
}

    public function attachProducts(Request $request, User $influencer)
    {
        abort_if($influencer->type !== 'influencer', 404);

        $data = $request->validate([
            'products'                             => 'required|array|min:1',
            'products.*.product_id'                => 'required|integer|distinct|exists:products,id',
            'products.*.commission_percentage'     => 'nullable|numeric|min:0|max:100',
            'products.*.is_active'                 => 'sometimes|boolean',
        ]);

        $attach = [];
        foreach ($data['products'] as $item) {
            $pivot = [];

            if (array_key_exists('commission_percentage', $item)) {
                $pivot['commission_percentage'] = $item['commission_percentage'];
            }

            // is_active صريح فقط؛ لو مش مبعوت ما نلمسش القيمة القديمة
            if (array_key_exists('is_active', $item)) {
                $pivot['is_active'] = (bool) $item['is_active'];
            } else {
                // منتج جديد فقط → نشط افتراضيًا
                $already = $influencer->influencerProducts()
                    ->where('products.id', $item['product_id'])
                    ->exists();
                if (!$already) {
                    $pivot['is_active'] = true;
                }
            }

            $attach[$item['product_id']] = $pivot;
        }

        $influencer->influencerProducts()->syncWithoutDetaching($attach);

        AuditLog::record('influencer.products_attached', $influencer, [
            'product_ids' => array_keys($attach),
        ]);

        // ربط/تفعيل منتجات المؤثر بيغيّر influencer_picks مباشرةً
        HomeController::flush();

        return response()->json([
            'message'  => 'تم ربط المنتجات بالمؤثر بنجاح',
            'products' => $influencer->influencerProducts()->get(),
        ]);
    }

    public function destroy(User $influencer)
    {
        abort_if($influencer->type !== 'influencer', 404);

        // منع الحذف لو فيه رصيد أو سحوبات/عمولات معلّقة
        $wallet = $influencer->wallet;

        if ($wallet && (round((float) $wallet->balance, 2) !== 0.0 || (float) $wallet->total_earned > 0)) {
            return response()->json([
                'message' => 'لا يمكن حذف مؤثر لديه رصيد أو أرباح مسجّلة. عطّل الحساب بدلاً من الحذف.',
            ], 422);
        }

        $hasPendingWithdrawals = \App\Models\WithdrawalRequest::where('influencer_id', $influencer->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($hasPendingWithdrawals) {
            return response()->json([
                'message' => 'لا يمكن حذف مؤثر لديه طلبات سحب معلّقة أو معتمدة.',
            ], 422);
        }

        $hasPendingCommissions = $influencer->commissions()
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($hasPendingCommissions) {
            return response()->json([
                'message' => 'لا يمكن حذف مؤثر لديه عمولات معلّقة أو متاحة.',
            ], 422);
        }

        $influencer->tokens()->delete();
        $influencer->delete();

        AuditLog::record('influencer.deleted', $influencer, ['email' => $influencer->email]);

        HomeController::flush();

        return response()->json(['message' => 'تم حذف المؤثر بنجاح']);
    }

    public function generateLink(Request $request, User $influencer)
    {
        abort_if($influencer->type !== 'influencer', 404);

        $request->validate([
            'product_id' => 'nullable|exists:products,id',
        ]);

        $productId = $request->product_id;

        // الرابط الخاص بمنتج لازم يكون المنتج مربوط بالمؤثر ونشط
        if ($productId !== null) {
            $attached = $influencer->influencerProducts()
                ->where('products.id', $productId)
                ->wherePivot('is_active', true)
                ->exists();

            if (!$attached) {
                return response()->json([
                    'message' => 'المنتج غير مربوط بهذا المؤثر أو الربط غير نشط. اربطه أولاً من شاشة المنتجات.',
                ], 422);
            }
        }

        $link = $this->linkService->generate($influencer, $productId);

        return response()->json($link, 201);
    }
}
