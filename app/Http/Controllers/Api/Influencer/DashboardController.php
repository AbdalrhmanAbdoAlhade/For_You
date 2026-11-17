<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use App\Models\InfluencerCommission;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionalLink;
use App\Services\InfluencerCommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
            'group_by' => 'nullable|in:day,month',
        ]);

        $influencer = $request->user();
        abort_if($influencer->type !== 'influencer', 403);

        // ===== Stats (الموجودة) =====
        $sales = Order::where('influencer_id', $influencer->id)
            ->whereIn('status', Order::SALE_STATUSES)
            ->selectRaw('COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS sales_total')
            ->first();

        $totalCommission = $influencer->commissions()
            ->whereIn('status', InfluencerCommission::EARNED_STATUSES)
            ->sum('commission_amount');

        $wallet = $influencer->wallet;

        // ===== NEW: Clicks + Conversion rate =====
        // clicks/conversions aggregate على promotional_links — مفيش فلترة زمنية (schema)
        $linkStats = PromotionalLink::where('influencer_id', $influencer->id)
            ->selectRaw('COALESCE(SUM(clicks), 0) AS total_clicks, COALESCE(SUM(conversions), 0) AS total_conversions')
            ->first();

        $totalClicks = (int) $linkStats->total_clicks;
        $totalConversions = (int) $linkStats->total_conversions;
        $conversionRate = $totalClicks > 0
            ? round(($totalConversions / $totalClicks) * 100, 2)
            : 0.0;

        $stats = [
            'total_sales'      => (float) $sales->sales_total,
            'total_orders'     => (int) $sales->orders_count,
            'total_commission' => (float) $totalCommission,
            'wallet_balance'   => (float) ($wallet?->balance ?? 0),
            'total_earned'     => (float) ($wallet?->total_earned ?? 0),
            'total_withdrawn'  => (float) ($wallet?->total_withdrawn ?? 0),
            'total_clicks'     => $totalClicks,
            'conversion_rate'  => $conversionRate,
        ];

        // ===== NEW: Sales chart =====
        $salesChart = $this->buildSalesChart($influencer, $request);

        // ===== NEW: Top products =====
        $topProducts = $this->buildTopProducts($influencer);

        // ===== Recent commissions (الموجودة) =====
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

        // ===== Products (الموجودة) =====
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
                'commission_percentage' => InfluencerCommissionService::resolvePercentage($p->pivot->commission_percentage),
            ]);

        return response()->json([
            'stats'              => $stats,
            'sales_chart'        => $salesChart,
            'top_products'       => $topProducts,
            'recent_commissions' => $recentCommissions,
            'products'           => $products,
        ]);
    }

    /**
     * مبيعات يومية/شهرية (delivered فقط، المؤثر الحالي)
     * @return array<int, array{date:string, orders_count:int, sales_total:float}>
     */
  protected function buildSalesChart($influencer, Request $request): array
{
    $groupBy = $request->input('group_by', 'day');

    $from = $request->filled('from')
        ? Carbon::parse($request->from)->startOfDay()
        : now()->subDays(30)->startOfDay();

    $to = $request->filled('to')
        ? Carbon::parse($request->to)->endOfDay()
        : now()->endOfDay();

    $driver = \DB::connection()->getDriverName();

    if ($groupBy === 'month') {
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";
    } else {
        $dateExpr = 'DATE(created_at)'; // مدعوم في SQLite + MySQL
    }

    return Order::where('influencer_id', $influencer->id)
        ->whereIn('status', Order::SALE_STATUSES)
        ->whereNull('deleted_at')
        ->whereBetween('created_at', [$from, $to])
        ->selectRaw("{$dateExpr} AS date, COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS sales_total")
        ->groupByRaw($dateExpr)
        ->orderBy('date')
        ->get()
        ->map(fn ($row) => [
            'date'         => $row->date,
            'orders_count' => (int) $row->orders_count,
            'sales_total'  => (float) $row->sales_total,
        ])
        ->all();
}
    /**
     * أكثر 10 منتجات من حيث الكمية المباعة عبر commissions المؤثر.
     * المصدر: influencer_commissions (يحترم قيد "رابط منتج واحد") JOIN order_items.
     * الفلترة: commission status = earned, order status = delivered.
     */
    protected function buildTopProducts($influencer, int $limit = 10): array
    {
        $rows = DB::table('influencer_commissions as ic')
            ->join('order_items as oi', 'oi.id', '=', 'ic.order_item_id')
            ->join('orders as o', 'o.id', '=', 'ic.order_id')
            ->where('ic.influencer_id', $influencer->id)
            ->whereIn('ic.status', InfluencerCommission::EARNED_STATUSES)
            ->whereIn('o.status', Order::SALE_STATUSES)
            ->whereNull('o.deleted_at')
            ->whereNotNull('ic.order_item_id')
            ->groupBy('ic.product_id', 'oi.product_name')
            ->selectRaw('
                ic.product_id AS product_id,
                oi.product_name AS product_name,
                SUM(oi.quantity) AS sold_quantity,
                SUM(oi.total) AS total_sales,
                SUM(ic.commission_amount) AS total_commission,
                COUNT(DISTINCT ic.order_id) AS orders_count
            ')
            ->orderByDesc('sold_quantity')
            ->limit($limit)
            ->get();

        $productIds = $rows->pluck('product_id')->filter()->unique()->all();
        $images = empty($productIds)
            ? collect()
            : Product::whereIn('id', $productIds)->get()
                ->mapWithKeys(fn ($p) => [$p->id => ($p->images[0] ?? null)]);

        return $rows->map(fn ($row) => [
            'product_id'       => $row->product_id ? (int) $row->product_id : null,
            'product_name'     => $row->product_name,
            'product_image'    => $row->product_id ? ($images[$row->product_id] ?? null) : null,
            'sold_quantity'    => (int) $row->sold_quantity,
            'total_sales'      => (float) $row->total_sales,
            'total_commission' => (float) $row->total_commission,
            'orders_count'     => (int) $row->orders_count,
        ])->all();
    }
}
