<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use App\Models\InfluencerCommission;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SalesController extends Controller
{
    public function index(Request $request)
    {
                $request->validate([
            'status' => 'nullable|in:new,confirmed,processing,ready_for_shipping,shipped,delivered,cancelled,returned',
            'from'   => 'nullable|date',
            'to'     => 'nullable|date|after_or_equal:from',
        ]);

        $userId = $request->user()->id;

        $base = Order::query()
            ->where('influencer_id', $userId)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()));

        $totals = (clone $base)
            ->whereNotIn('status', Order::NON_SALE_STATUSES)
            ->selectRaw('COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS sales_total')
            ->first();

        $commissionTotal = InfluencerCommission::where('influencer_id', $userId)
            ->whereIn('status', InfluencerCommission::EARNED_STATUSES)
            ->whereIn('order_id', (clone $base)->select('orders.id'))
            ->sum('commission_amount');

        $orders = (clone $base)
            ->with('items:id,order_id,product_name,quantity,total')
            ->withSum(['commissions as commission_amount' => function ($q) {
                $q->whereIn('status', InfluencerCommission::EARNED_STATUSES);
            }], 'commission_amount')
            ->latest()
            ->paginate(15);

        // من غير بيانات العميل (اسم، تليفون، عنوان)
        $orders->through(fn ($o) => [
            'order_number'      => $o->order_number,
            'status'            => $o->status,
            'total'             => (float) $o->total,
            'commission_amount' => (float) ($o->commission_amount ?? 0),
            'items'             => $o->items->map(fn ($i) => [
                'product_name' => $i->product_name,
                'quantity'     => $i->quantity,
                'total'        => (float) $i->total,
            ]),
            'created_at'        => $o->created_at?->toDateTimeString(),
        ]);

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
