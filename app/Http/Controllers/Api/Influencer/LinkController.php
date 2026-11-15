<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LinkController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'is_active'  => 'nullable|boolean',
            'product_id' => 'nullable|integer',
        ]);

        $links = $request->user()->promotionalLinks()
            ->with('product:id,name')
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->latest()
            ->paginate(15);

        $links->through(fn ($l) => [
            'id'              => $l->id,
            'code'            => $l->code,
            'full_url'        => $l->full_url,
            'product'         => $l->product ? ['id' => $l->product->id, 'name' => $l->product->name] : null,
            'clicks'          => $l->clicks,
            'conversions'     => $l->conversions,
            'conversion_rate' => $l->clicks > 0 ? round($l->conversions / $l->clicks * 100, 2) : 0,
            'is_active'       => $l->is_active,
            'expires_at'      => $l->expires_at?->toDateTimeString(),
            'created_at'      => $l->created_at?->toDateTimeString(),
        ]);

        return response()->json($links);
    }
}
