<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $influencer = $request->user();

        abort_if($influencer->type !== 'influencer', 403);

        $stats = [
            'total_sales'     => $influencer->ordersAsInfluencer()->sum('total'),
            'total_orders'    => $influencer->ordersAsInfluencer()->count(),
            'total_commission'=> $influencer->commissions()->where('status', 'approved')->sum('commission_amount'),
            'wallet_balance'  => $influencer->wallet?->balance ?? 0,
            'total_earned'    => $influencer->wallet?->total_earned ?? 0,
        ];

        $recentCommissions = $influencer->commissions()
            ->with('order')
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'stats'              => $stats,
            'recent_commissions' => $recentCommissions,
            'products'           => $influencer->influencerProducts,
        ]);
    }
}
