<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InfluencerCommission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Product;

class ReportController extends Controller
{
    /** العمولات اللي اتكسبت فعلاً */
    protected const EARNED_COMMISSION_STATUSES = ['approved', 'paid'];

    /** الحالات اللي بتتحسب مبيعات فعلية (delivered فقط) */
    protected function saleStatuses(): array
    {
        return Order::SALE_STATUSES;
    }
  		public function customers(Request $request)
{
    $timezone = config('app.timezone');
    $monthStart = now($timezone)->startOfMonth();

    // 1) عملاء جدد الشهر ده
    $newThisMonth = User::customers()
        ->where('created_at', '>=', $monthStart)
        ->count();

    // 2) عملاء عائدين (>= 2 أوردر delivered)
    $returningBuyers = (int) DB::table('orders')
        ->select('customer_id')
        ->whereIn('status', $this->saleStatuses())
        ->whereNull('deleted_at')
        ->groupBy('customer_id')
        ->havingRaw('COUNT(*) >= 2')
        ->get()
        ->count();

    // 3) جايين من influencer link
    $cameViaInfluencer = (int) DB::table('orders')
        ->whereIn('status', $this->saleStatuses())
        ->whereNull('deleted_at')
        ->whereNotNull('influencer_id')
        ->distinct()
        ->count('customer_id');

    // 4) متوسط قيمة الأوردر
    $avgOrderValue = (float) (Order::whereIn('status', $this->saleStatuses())
        ->whereNull('deleted_at')
        ->avg('total') ?? 0);

    return response()->json([
        'new_this_month'      => (int) $newThisMonth,
        'returning_buyers'    => $returningBuyers,
        'came_via_influencer' => $cameViaInfluencer,
        'avg_order_value'     => round($avgOrderValue, 2),
    ]);
}

    public function sales(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $timezone = config('app.timezone');

        $from = Carbon::parse($request->get('from', now($timezone)->subDays(30)), $timezone)->startOfDay();
        $to   = Carbon::parse($request->get('to', now($timezone)), $timezone)->endOfDay();

        $sales = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', $this->saleStatuses())
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as total_sales')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
            'sales'   => $sales,
            'summary' => [
                'total_orders' => (int) $sales->sum('orders_count'),
                'total_sales'  => (float) $sales->sum('total_sales'),
            ],
        ]);
    }

    public function influencers(Request $request)
    {
        $data = User::influencers()
            ->withSum(['commissions as total_commission' => function ($q) {
                $q->whereIn('status', self::EARNED_COMMISSION_STATUSES);
            }], 'commission_amount')
            ->withCount(['ordersAsInfluencer as total_orders' => function ($q) {
                $q->whereIn('status', $this->saleStatuses());
            }])
            ->withSum(['ordersAsInfluencer as total_sales' => function ($q) {
                $q->whereIn('status', $this->saleStatuses());
            }], 'total')
            ->with('wallet')
            ->orderByRaw('COALESCE(total_commission, 0) DESC')
            ->paginate(20);

        // أرقام مش strings ومفيش null
        $data->getCollection()->transform(function ($influencer) {
            $influencer->total_commission = (float) ($influencer->total_commission ?? 0);
            $influencer->total_sales      = (float) ($influencer->total_sales ?? 0);
            return $influencer;
        });

        return response()->json($data);
    }

   public function topProducts(Request $request)
{
    $request->validate([
        'from'  => 'nullable|date',
        'to'    => 'nullable|date|after_or_equal:from',
        'limit' => 'nullable|integer|min:1|max:100',
    ]);

    $timezone = config('app.timezone');
    $from = $request->filled('from') ? Carbon::parse($request->from, $timezone)->startOfDay() : null;
    $to   = $request->filled('to')   ? Carbon::parse($request->to, $timezone)->endOfDay()   : null;

    $query = OrderItem::query()
        ->join('orders', 'orders.id', '=', 'order_items.order_id')
        ->whereNull('orders.deleted_at')
        ->whereIn('orders.status', $this->saleStatuses());

    if ($from) $query->where('orders.created_at', '>=', $from);
    if ($to)   $query->where('orders.created_at', '<=', $to);

    $rows = $query
        ->select(
            'order_items.product_id',
            DB::raw('MAX(order_items.product_name) as name'),
            DB::raw('SUM(order_items.quantity) as sold_quantity'),
            DB::raw('SUM(order_items.total) as total_revenue'),
            DB::raw('SUM(CASE WHEN order_items.commission_percentage IS NOT NULL THEN order_items.quantity ELSE 0 END) as inf_qty'),
            DB::raw('SUM(CASE WHEN order_items.commission_percentage IS NOT NULL THEN order_items.total ELSE 0 END) as inf_revenue')
        )
        ->groupBy('order_items.product_id')
        ->orderByDesc('sold_quantity')
        ->limit((int) $request->get('limit', 20))
        ->get();

    // eager load للمنتجات + الأقسام
    $productIds = $rows->pluck('product_id')->all();
    $products = Product::with('category:id,name,name_en')
        ->whereIn('id', $productIds)
        ->get()
        ->keyBy('id');

    return response()->json($rows->map(function ($row) use ($products) {
        $product = $products->get($row->product_id);
        $category = $product?->category;

        return [
            'product_id'       => $row->product_id,
            'name'             => $row->name,
            'category'         => $category ? [
                'id'   => $category->id,
                'name' => $category->localized('name'),
            ] : null,
            'sold_quantity'    => (int) $row->sold_quantity,
            'total_revenue'    => (float) $row->total_revenue,
            'influencer_sales' => [
                'sold_quantity' => (int) $row->inf_qty,
                'total_revenue' => (float) $row->inf_revenue,
            ],
            'remaining_stock'  => (int) ($product?->stock ?? 0),
        ];
    }));
}
  
  public function categories(Request $request)
{
    $request->validate([
        'from'  => 'nullable|date',
        'to'    => 'nullable|date|after_or_equal:from',
        'limit' => 'nullable|integer|min:1|max:200',
    ]);

    $timezone = config('app.timezone');
    $from = $request->filled('from') ? Carbon::parse($request->from, $timezone)->startOfDay() : now($timezone)->subDays(30);
    $to   = $request->filled('to')   ? Carbon::parse($request->to, $timezone)->endOfDay()   : now($timezone);

    $rows = DB::table('order_items')
        ->join('orders', 'orders.id', '=', 'order_items.order_id')
        ->join('products', 'products.id', '=', 'order_items.product_id')
        ->join('categories', 'categories.id', '=', 'products.category_id')
        ->whereNull('orders.deleted_at')
        ->whereIn('orders.status', $this->saleStatuses())
        ->whereBetween('orders.created_at', [$from, $to])
        ->select(
            'categories.id as category_id',
            'categories.name as name',
            'categories.name_en as name_en',
            DB::raw('SUM(order_items.quantity) as sold_quantity'),
            DB::raw('SUM(order_items.total) as total_revenue'),
            DB::raw('SUM(CASE WHEN order_items.commission_percentage IS NOT NULL THEN order_items.quantity ELSE 0 END) as inf_qty'),
            DB::raw('SUM(CASE WHEN order_items.commission_percentage IS NOT NULL THEN order_items.total ELSE 0 END) as inf_revenue'),
            DB::raw('COUNT(DISTINCT products.id) as products_count')
        )
        ->groupBy('categories.id', 'categories.name', 'categories.name_en')
        ->orderByDesc('total_revenue')
        ->limit((int) $request->get('limit', 100))
        ->get();

    $isEn = app()->getLocale() === 'en';

    return response()->json($rows->map(fn ($r) => [
        'category_id'              => (int) $r->category_id,
        'category_name'            => $isEn && $r->name_en ? $r->name_en : $r->name,
        'sold_quantity'            => (int) $r->sold_quantity,
        'total_revenue'            => (float) $r->total_revenue,
        'influencer_sold_quantity' => (int) $r->inf_qty,
        'influencer_revenue'       => (float) $r->inf_revenue,
        'products_count'           => (int) $r->products_count,
    ]));
}
public function salesSources(Request $request)
{
    $request->validate([
        'from' => 'nullable|date',
        'to'   => 'nullable|date|after_or_equal:from',
    ]);

    $timezone = config('app.timezone');
    $from = $request->filled('from')
        ? Carbon::parse($request->from, $timezone)->startOfDay()
        : now($timezone)->subDays(30)->startOfDay();
    $to = $request->filled('to')
        ? Carbon::parse($request->to, $timezone)->endOfDay()
        : now($timezone)->endOfDay();

    $row = Order::whereIn('status', $this->saleStatuses())
        ->whereBetween('created_at', [$from, $to])
        ->selectRaw("
            COALESCE(SUM(CASE WHEN influencer_id IS NULL THEN total END), 0) AS direct_sales,
            COALESCE(SUM(CASE WHEN influencer_id IS NULL THEN 1 ELSE 0 END), 0) AS direct_orders,
            COALESCE(SUM(CASE WHEN influencer_id IS NOT NULL THEN total END), 0) AS influencer_sales,
            COALESCE(SUM(CASE WHEN influencer_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS influencer_orders
        ")
        ->first();

    $direct     = (float) $row->direct_sales;
    $influencer = (float) $row->influencer_sales;
    $total      = $direct + $influencer;

    return response()->json([
        'from'        => $from->toDateString(),
        'to'          => $to->toDateString(),
        'total_sales' => round($total, 2),
        'sources'     => [
            [
                'key'          => 'direct',
                'sales'        => round($direct, 2),
                'orders_count' => (int) $row->direct_orders,
                'percentage'   => $total > 0 ? round($direct / $total * 100, 1) : 0,
            ],
            [
                'key'          => 'influencer',
                'sales'        => round($influencer, 2),
                'orders_count' => (int) $row->influencer_orders,
                'percentage'   => $total > 0 ? round($influencer / $total * 100, 1) : 0,
            ],
        ],
    ]);
}

public function orderOutcomes(Request $request)
{
    $request->validate([
        'from' => 'nullable|date',
        'to'   => 'nullable|date|after_or_equal:from',
    ]);

    $timezone = config('app.timezone');
    $from = $request->filled('from')
        ? Carbon::parse($request->from, $timezone)->startOfDay()
        : now($timezone)->subDays(30)->startOfDay();
    $to = $request->filled('to')
        ? Carbon::parse($request->to, $timezone)->endOfDay()
        : now($timezone)->endOfDay();

    // الطلبات اللي وصلت لحالة نهائية بس
    $row = Order::whereBetween('created_at', [$from, $to])
        ->whereIn('status', ['delivered', 'cancelled', 'returned'])
        ->selectRaw("
            COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled,
            COALESCE(SUM(CASE WHEN status = 'returned'  THEN 1 ELSE 0 END), 0) AS returned
        ")
        ->first();

    $delivered = (int) $row->delivered;
    $cancelled = (int) $row->cancelled;
    $returned  = (int) $row->returned;
    $total     = $delivered + $cancelled + $returned;

    $pct = fn (int $n) => $total > 0 ? round($n / $total * 100, 1) : 0;

    return response()->json([
        'from'         => $from->toDateString(),
        'to'           => $to->toDateString(),
        'total_orders' => $total,
        'outcomes'     => [
            ['key' => 'delivered', 'count' => $delivered, 'percentage' => $pct($delivered)],
            ['key' => 'cancelled', 'count' => $cancelled, 'percentage' => $pct($cancelled)],
            ['key' => 'returned',  'count' => $returned,  'percentage' => $pct($returned)],
        ],
    ]);
}
    public function overview()
    {
        return response()->json([
            'total_sales'         => (float) Order::whereIn('status', $this->saleStatuses())->sum('total'),
            'total_orders'        => Order::count(),
            'sales_orders'        => Order::whereIn('status', $this->saleStatuses())->count(),
            'total_customers'     => User::customers()->count(),
            'total_influencers'   => User::influencers()->count(),
            'total_commissions'   => (float) InfluencerCommission::whereIn('status', self::EARNED_COMMISSION_STATUSES)
                                        ->sum('commission_amount'),
            'pending_withdrawals' => (float) WithdrawalRequest::where('status', 'pending')->sum('amount'),
        ]);
    }
}
