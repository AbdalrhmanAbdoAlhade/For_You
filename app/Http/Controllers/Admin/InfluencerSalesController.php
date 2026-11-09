<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InfluencerCommission;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InfluencerSalesController extends Controller
{
    /** GET admin/influencer-sales?influencer_id=&status=&from=&to= */
    public function index(Request $request)
    {
        return $this->respond($request, $request->integer('influencer_id') ?: null);
    }

    /** GET admin/influencers/{influencer}/sales */
    public function forInfluencer(Request $request, User $influencer)
    {
        abort_if($influencer->type !== 'influencer', 404);

        return $this->respond($request, $influencer->id);
    }

    protected function respond(Request $request, ?int $influencerId)
    {
        $request->validate([
            'status' => 'nullable|string',
            'from'   => 'nullable|date',
            'to'     => 'nullable|date|after_or_equal:from',
        ]);

        $base = Order::query()
            ->whereNotNull('influencer_id')
            ->when($influencerId, fn ($q, $id) => $q->where('influencer_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()));

        $totals = (clone $base)
            ->whereNotIn('status', Order::NON_SALE_STATUSES)
            ->selectRaw('COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS sales_total')
            ->first();

        $commissionTotal = InfluencerCommission::whereIn('status', InfluencerCommission::EARNED_STATUSES)
            ->whereIn('order_id', (clone $base)->select('orders.id'))
            ->sum('commission_amount');

        $orders = (clone $base)
            ->with(['influencer:id,name', 'customer:id,name'])
            ->withSum(['commissions as commission_amount' => function ($q) {
                $q->whereIn('status', InfluencerCommission::EARNED_STATUSES);
            }], 'commission_amount')
            ->latest()
            ->paginate(20);

        return response()->json([
            'summary' => [
                'orders_count'     => (int) $totals->orders_count,
                'sales_total'      => (float) $totals->sales_total,
                'commission_total' => (float) $commissionTotal,
            ],
            'orders' => $orders,
        ]);
    }
}
