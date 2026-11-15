<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use App\Services\InfluencerCommissionService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = $request->user()->influencerProducts()
            ->wherePivot('is_active', true)
            ->with('category:id,name')
            ->get()
            ->map(fn ($p) => [
                'id'                    => $p->id,
                'name'                  => $p->name,
                'price'                 => (float) $p->price,
                'final_price'           => (float) $p->final_price,
                'images'                => $p->images ?? [],
                'category'              => $p->category?->name,
                'is_active'             => $p->is_active,
                'commission_percentage' => InfluencerCommissionService::resolvePercentage($p->pivot->commission_percentage),
            ]);

        return response()->json($products);
    }

    public function percentage(Request $request)
    {
        $products = $request->user()->influencerProducts()
            ->wherePivot('is_active', true)
            ->get()
            ->map(fn ($p) => [
                'product_id'            => $p->id,
                'name'                  => $p->name,
                'commission_percentage' => InfluencerCommissionService::resolvePercentage($p->pivot->commission_percentage),
            ]);

        return response()->json([
            'default_percentage' => (float) config('services.influencer.default_commission', 10),
            'products'           => $products,
        ]);
    }
}
