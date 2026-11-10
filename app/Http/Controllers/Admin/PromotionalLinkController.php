<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PromotionalLink;
use Illuminate\Http\Request;

class PromotionalLinkController extends Controller
{
    public function index(Request $request)
    {
        $links = PromotionalLink::with(['influencer:id,name', 'product:id,name'])
            ->withSum(['orders as sales_total' => function ($q) {
                $q->whereNotIn('status', Order::NON_SALE_STATUSES);
            }], 'total')
            ->when($request->influencer_id, fn ($q, $id) => $q->where('influencer_id', $id))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->search, fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20);

        $links->through(fn ($l) => $this->format($l));

        return response()->json($links);
    }

    public function show(PromotionalLink $link)
    {
        $link->load(['influencer:id,name', 'product:id,name'])
             ->loadSum(['orders as sales_total' => function ($q) {
                 $q->whereNotIn('status', Order::NON_SALE_STATUSES);
             }], 'total');

        return response()->json($this->format($link));
    }

    public function update(Request $request, PromotionalLink $link)
    {
        $data = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'is_active'  => 'boolean',
            'expires_at' => 'nullable|date',
        ]);

        if (
    array_key_exists('product_id', $data)
    && (int) ($data['product_id'] ?? 0) !== (int) ($link->product_id ?? 0)
    && $link->orders()->exists()
) {
    return response()->json([
        'message' => 'لا يمكن تغيير المنتج المرتبط برابط تم استخدامه في طلبات سابقة',
    ], 422);
}
        $link->update($data);

        return response()->json($this->format($link->load(['influencer:id,name', 'product:id,name'])));
    }

    public function toggle(PromotionalLink $link)
    {
        $link->update(['is_active' => !$link->is_active]);

        return response()->json([
            'message'   => $link->is_active ? 'تم تفعيل الرابط' : 'تم إيقاف الرابط',
            'is_active' => $link->is_active,
        ]);
    }

    protected function format(PromotionalLink $l): array
    {
        return [
            'id'              => $l->id,
            'code'            => $l->code,
            'full_url'        => $l->full_url,
            'influencer'      => $l->influencer ? ['id' => $l->influencer->id, 'name' => $l->influencer->name] : null,
            'product'         => $l->product ? ['id' => $l->product->id, 'name' => $l->product->name] : null,
            'clicks'          => $l->clicks,
            'conversions'     => $l->conversions,
            'conversion_rate' => $l->clicks > 0 ? round($l->conversions / $l->clicks * 100, 2) : 0,
            'sales_total'     => (float) ($l->sales_total ?? 0),
            'is_active'       => $l->is_active,
            'expires_at'      => $l->expires_at?->toDateTimeString(),
            'created_at'      => $l->created_at?->toDateTimeString(),
        ];
    }
}
