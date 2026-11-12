<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InfluencerCommission;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // الحالات اللي مبتتحسبش مبيعات
        $excluded = "('cancelled','returned')";

        // استعلام واحد بدل 8 على جدول الطلبات
        $orders = Order::selectRaw("
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN status NOT IN {$excluded} THEN total END), 0) AS total_sales,
            COALESCE(SUM(CASE WHEN influencer_id IS NOT NULL AND status NOT IN {$excluded} THEN total END), 0) AS influencer_sales,
            COALESCE(SUM(CASE WHEN status IN ('new','confirmed','processing','ready_for_shipping') THEN 1 ELSE 0 END), 0) AS pending_orders,
            COALESCE(SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END), 0) AS shipped_orders,
            COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered_orders,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
            COALESCE(SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END), 0) AS returned_orders
        ")->first();

        $stats = [
            // أعداد تشغيلية
            'total_orders'      => (int) $orders->total_orders,
            'pending_orders'    => (int) $orders->pending_orders,
            'shipped_orders'    => (int) $orders->shipped_orders,
            'delivered_orders'  => (int) $orders->delivered_orders,
            'cancelled_orders'  => (int) $orders->cancelled_orders,
            'returned_orders'   => (int) $orders->returned_orders,
            'total_customers'   => User::customers()->count(),
            'total_products'    => Product::count(),
            'total_categories'  => Category::count(),
            'total_employees'   => User::employees()->count(),
            'total_influencers' => User::influencers()->count(),
        ];

        // الأرقام المالية لمن معاه reports.view فقط (الأدمن عنده كل الصلاحيات)
        $canSeeFinance = $request->user()->hasPermission('reports.view');

        if ($canSeeFinance) {
            $stats += [
                'total_sales'        => (float) $orders->total_sales,
                'influencer_sales'   => (float) $orders->influencer_sales,
                'influencer_profits' => (float) InfluencerCommission::whereIn('status', ['approved', 'paid'])
                                            ->sum('commission_amount'),
                'total_payments'     => (float) Payment::where('status', 'paid')->sum('amount'),
                'pending_payments'   => Payment::where('status', 'pending')->count(),
            ];
        }

        $recentOrders = Order::with(['customer:id,name,phone', 'influencer:id,name'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'stats'         => $stats,
            'recent_orders' => $recentOrders,
        ]);
    }
}
