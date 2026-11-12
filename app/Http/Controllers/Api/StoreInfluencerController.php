<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\User;
use Illuminate\Http\Request;

class StoreInfluencerController extends Controller
{
    public function index()
    {
        $activePivot = function ($q) {
            $q->where('products.is_active', true)->where('influencer_product.is_active', true);
        };

        $influencers = User::influencers()->active()
            ->whereHas('influencerProducts', $activePivot)
            ->withCount(['influencerProducts as products_count' => $activePivot])
            ->orderBy('name')
            ->paginate(12);

        // بيانات عامة بس (من غير إيميل أو تليفون)
        $influencers->through(fn ($u) => [
            'id'             => $u->id,
            'name'           => $u->name,
            'avatar'         => $u->avatar,
            'products_count' => $u->products_count,
        ]);

        return response()->json($influencers);
    }

    public function products(Request $request, int $id)
    {
        $influencer = User::influencers()->active()->findOrFail($id);

        $products = $influencer->influencerProducts()
            ->where('products.is_active', true)
            ->wherePivot('is_active', true)
            ->with('category')
            ->latest('products.created_at')
            ->paginate(15);

        return ProductResource::collection($products)->additional([
            'influencer' => [
                'id'     => $influencer->id,
                'name'   => $influencer->name,
                'avatar' => $influencer->avatar,
            ],
        ]);
    }
}
