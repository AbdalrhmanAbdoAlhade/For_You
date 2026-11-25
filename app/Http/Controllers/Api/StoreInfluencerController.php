<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InfluencerResource;
use App\Models\Brand;
use App\Models\User;
use App\Services\PreviewImageService;
use Illuminate\Http\Request;

class StoreInfluencerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('type', 'influencer')
            ->where('is_active', 1);

        if ($request->has('brand_id')) {
            $brandIdParam = $request->brand_id;

            $brand = Brand::where('slug', $brandIdParam)
                ->orWhere('id', $brandIdParam)
                ->first();

            if (!$brand) {
                return InfluencerResource::collection(collect([]));
            }

            $influencers = $query
                ->whereHas('brands', function ($q) use ($brand) {
                    $q->where('brand_id', $brand->id);
                })
                ->with([
                    'influencerProducts' => function ($q) use ($brand) {
                        $q->where('products.brand_id', $brand->id)
                            ->where('products.is_active', true)
                            ->wherePivot('is_active', true)
                            ->with('category')
                            ->withCount('variants')
                            ->latest('products.created_at')
                            ->orderByDesc('products.id');
                    },
                ])
                ->get();

            $influencers->each(function ($influencer) use ($brand) {
                $influencer->preview_images =
                    PreviewImageService::getInfluencerBrandPreviewImages(
                        $influencer->id,
                        $brand->id,
                        $brand->logo
                    );
            });

            return InfluencerResource::collection($influencers);
        }

        $influencers = $query->paginate(20);

        return InfluencerResource::collection($influencers);
    }
}
