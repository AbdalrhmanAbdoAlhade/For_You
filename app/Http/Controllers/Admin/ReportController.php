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

class ReportController extends Controller
{
    /** الحالات اللي مبتتحسبش مبيعات */
    protected const EXCLUDED_STATUSES = ['cancelled', 'returned'];

    /** العمولات اللي اتكسبت فعلاً */
    protected const EARNED_COMMISSION_STATUSES = ['approved', 'paid'];

    public function sales(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = Carbon::parse($request->get('from', now()->subDays(30)))->startOfDay();
        $to   = Carbon::parse($request->get('to', now()))->endOfDay();

        $sales = Order::whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
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
                $q->whereNotIn('status', self::EXCLUDED_STATUSES);
            }])
            ->withSum(['ordersAsInfluencer as total_sales' => function ($q) {
                $q->whereNotIn('status', self::EXCLUDED_STATUSES);
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

        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', self::EXCLUDED_STATUSES);

        if ($request->filled('from')) {
            $query->where('orders.created_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('orders.created_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        $products = $query
            ->select(
                'order_items.product_id',
                DB::raw('MAX(order_items.product_name) as name'),
                DB::raw('SUM(order_items.quantity) as sold_quantity'),
                DB::raw('SUM(order_items.total) as total_revenue')
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('sold_quantity')
            ->limit((int) $request->get('limit', 20))
            ->get()
            ->map(fn ($row) => [
                'product_id'    => $row->product_id,
                'name'          => $row->name,
                'sold_quantity' => (int) $row->sold_quantity,
                'total_revenue' => (float) $row->total_revenue,
            ]);

        return response()->json($products);
    }

    public function overview()
    {
        return response()->json([
            'total_sales'         => (float) Order::whereNotIn('status', self::EXCLUDED_STATUSES)->sum('total'),
            'total_orders'        => Order::count(),
            'total_customers'     => User::customers()->count(),
            'total_influencers'   => User::influencers()->count(),
            'total_commissions'   => (float) InfluencerCommission::whereIn('status', self::EARNED_COMMISSION_STATUSES)
                                        ->sum('commission_amount'),
            'pending_withdrawals' => (float) WithdrawalRequest::where('status', 'pending')->sum('amount'),
        ]);
    }
}
