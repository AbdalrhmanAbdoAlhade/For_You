<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use App\Models\InfluencerCommission;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $influencer = $request->user();

        abort_if($influencer->type !== 'influencer', 403);

        // المبيعات: من غير الطلبات الملغية والمرتجعة
        $sales = Order::where('influencer_id', $influencer->id)
            ->whereNotIn('status', Order::NON_SALE_STATUSES)
            ->selectRaw('COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS sales_total')
            ->first();

        // العمولات المكتسبة: approved + paid
        $totalCommission = $influencer->commissions()
            ->whereIn('status', InfluencerCommission::EARNED_STATUSES)
            ->sum('commission_amount');

        $wallet = $influencer->wallet;

        $stats = [
            'total_sales'      => (float) $sales->sales_total,
            'total_orders'     => (int) $sales->orders_count,
            'total_commission' => (float) $totalCommission,
            'wallet_balance'   => (float) ($wallet?->balance ?? 0),
            'total_earned'     => (float) ($wallet?->total_earned ?? 0),
            'total_withdrawn'  => (float) ($wallet?->total_withdrawn ?? 0),
        ];

        // آخر العمولات: بيانات مالية بس، من غير أي بيانات عن العميل
        $recentCommissions = $influencer->commissions()
            ->with([
                'order:id,order_number,status,created_at',
                'product:id,name',
            ])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($c) => [
                'id'                    => $c->id,
                'order_number'          => $c->order?->order_number,
                'order_status'          => $c->order?->status,
                'product_name'          => $c->product?->name,
                'order_total'           => (float) $c->order_total,
                'commission_percentage' => (float) $c->commission_percentage,
                'commission_amount'     => (float) $c->commission_amount,
                'status'                => $c->status,
                'created_at'            => $c->created_at?->toDateTimeString(),
            ]);

        // المنتجات المرتبطة بالمؤثر (حقول عامة + نسبته)
        $products = $influencer->influencerProducts()
            ->wherePivot('is_active', true)
            ->get()
            ->map(fn ($p) => [
                'id'                    => $p->id,
                'name'                  => $p->name,
                'price'                 => (float) $p->price,
                'final_price'           => (float) $p->final_price,
                'images'                => $p->images ?? [],
                'is_active'             => $p->is_active,
                'commission_percentage' => (float) $p->pivot->commission_percentage,
            ]);

        return response()->json([
            'stats'              => $stats,
            'recent_commissions' => $recentCommissions,
            'products'           => $products,
        ]);
    }
}