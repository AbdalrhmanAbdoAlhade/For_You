<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use App\Models\InfluencerCommission;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_sales'          => Order::where('status', '!=', 'cancelled')->sum('total'),
            'total_orders'         => Order::count(),
            'total_customers'      => User::customers()->count(),
            'total_products'       => Product::count(),
            'total_categories'     => Category::count(),
            'total_employees'      => User::employees()->count(),
            'total_influencers'    => User::influencers()->count(),
            'influencer_sales'     => Order::whereNotNull('influencer_id')->sum('total'),
            'influencer_profits'   => InfluencerCommission::where('status', 'approved')->sum('commission_amount'),
            'pending_orders'       => Order::whereIn('status', ['new', 'confirmed', 'processing'])->count(),
            'shipped_orders'       => Order::where('status', 'shipped')->count(),
            'delivered_orders'     => Order::where('status', 'delivered')->count(),
        ];

        $recentOrders = Order::with(['customer', 'influencer'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'stats'         => $stats,
            'recent_orders' => $recentOrders,
        ]);
    }
}
