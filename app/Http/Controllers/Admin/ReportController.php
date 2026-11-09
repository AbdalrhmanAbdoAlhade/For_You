<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\InfluencerCommission;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        $from = $request->get('from', now()->subDays(30)->toDateString());
        $to   = $request->get('to', now()->toDateString());

        $sales = Order::whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as total_sales')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'from'  => $from,
            'to'    => $to,
            'sales' => $sales,
            'summary' => [
                'total_orders' => $sales->sum('orders_count'),
                'total_sales'  => $sales->sum('total_sales'),
            ]
        ]);
    }

    public function influencers(Request $request)
    {
        $data = User::influencers()
            ->withSum(['commissions as total_commission' => function ($q) {
                $q->where('status', 'approved');
            }], 'commission_amount')
            ->withCount(['ordersAsInfluencer as total_orders'])
            ->with('wallet')
            ->orderByDesc('total_commission')
            ->paginate(20);

        return response()->json($data);
    }

    public function topProducts(Request $request)
    {
        $products = Product::withCount(['orderItems as sold_quantity' => function ($q) {
                $q->select(DB::raw('SUM(quantity)'));
            }])
            ->withSum('orderItems as total_revenue', 'total')
            ->orderByDesc('sold_quantity')
            ->take(20)
            ->get();

        return response()->json($products);
    }

    public function overview()
    {
        return response()->json([
            'total_sales'        => Order::where('status', '!=', 'cancelled')->sum('total'),
            'total_orders'       => Order::count(),
            'total_customers'    => User::customers()->count(),
            'total_influencers'  => User::influencers()->count(),
            'total_commissions'  => InfluencerCommission::where('status', 'approved')->sum('commission_amount'),
            'pending_withdrawals'=> \App\Models\WithdrawalRequest::where('status', 'pending')->sum('amount'),
        ]);
    }
}
