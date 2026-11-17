<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use App\Models\InfluencerCommission;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

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

        // الإجمالي = المبيعات المحققة فقط (delivered) — نفس تعريف التقارير
        $totals = (clone $base)
            ->whereIn('status', Order::SALE_STATUSES)
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
  /**
 * GET /api/influencer/sales/by-product
 * مبيعات المؤثر مجمعة حسب المنتج.
 * المصدر: influencer_commissions (EARNED only) JOIN order_items.
 */
public function byProduct(Request $request)
{
    $request->validate([
        'from'       => 'nullable|date',
        'to'         => 'nullable|date|after_or_equal:from',
        'product_id' => 'nullable|integer|exists:products,id',
        'sort_by'    => 'nullable|in:sold_quantity,total_sales,total_commission,orders_count',
    ]);

    $influencer = $request->user();
    abort_if($influencer->type !== 'influencer', 403);

    $query = DB::table('influencer_commissions as ic')
        ->join('order_items as oi', 'oi.id', '=', 'ic.order_item_id')
        ->join('orders as o', 'o.id', '=', 'ic.order_id')
        ->where('ic.influencer_id', $influencer->id)
        ->whereIn('ic.status', InfluencerCommission::EARNED_STATUSES)
        ->whereIn('o.status', Order::SALE_STATUSES)
        ->whereNull('o.deleted_at')
        ->whereNotNull('ic.order_item_id');

    if ($request->filled('from')) {
        $query->where('o.created_at', '>=', Carbon::parse($request->from)->startOfDay());
    }
    if ($request->filled('to')) {
        $query->where('o.created_at', '<=', Carbon::parse($request->to)->endOfDay());
    }
    if ($request->filled('product_id')) {
        $query->where('ic.product_id', (int) $request->product_id);
    }

    $rows = $query
        ->groupBy('ic.product_id', 'oi.product_name')
        ->selectRaw('
            ic.product_id AS product_id,
            oi.product_name AS product_name,
            SUM(oi.quantity) AS sold_quantity,
            SUM(oi.total) AS total_sales,
            SUM(ic.commission_amount) AS total_commission,
            COUNT(DISTINCT ic.order_id) AS orders_count,
            AVG(ic.commission_percentage) AS commission_percentage
        ')
        ->orderByDesc($request->input('sort_by', 'sold_quantity'))
        ->get();

    $productIds = $rows->pluck('product_id')->filter()->unique()->all();
    $images = empty($productIds)
        ? collect()
        : Product::whereIn('id', $productIds)->get()
            ->mapWithKeys(fn ($p) => [$p->id => ($p->images[0] ?? null)]);

    $products = $rows->map(fn ($row) => [
        'product_id'            => $row->product_id ? (int) $row->product_id : null,
        'product_name'          => $row->product_name,
        'product_image'         => $row->product_id ? ($images[$row->product_id] ?? null) : null,
        'sold_quantity'         => (int) $row->sold_quantity,
        'total_sales'           => (float) $row->total_sales,
        'total_commission'      => (float) $row->total_commission,
        'orders_count'          => (int) $row->orders_count,
        'commission_percentage' => $row->commission_percentage !== null
            ? round((float) $row->commission_percentage, 2)
            : null,
    ]);

    return response()->json([
        'summary' => [
            'products_count'   => $products->count(),
            'sold_quantity'    => (int) $products->sum('sold_quantity'),
            'total_sales'      => round((float) $products->sum('total_sales'), 2),
            'total_commission' => round((float) $products->sum('total_commission'), 2),
        ],
        'products' => $products,
    ]);
}
}
