<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Http\Resources\ProductResource;
use App\Models\Brand;
use App\Services\BrandFollowService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        protected BrandFollowService $follows
    ) {}

    public function index(Request $request)
    {
        $brands = Brand::active()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->withCount('followers')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30);

        $viewer   = $request->user('sanctum');
        $ids      = $brands->getCollection()->pluck('id')->all();
        $followed = $viewer ? $this->follows->followedAmong($viewer, $ids) : [];

        $brands->getCollection()->each(function ($brand) use ($viewer, $followed) {
            $brand->is_following = $viewer ? in_array($brand->id, $followed) : null;
        });

        return BrandResource::collection($brands);
    }

    public function show(Request $request, int $id)
    {
        $brand = Brand::active()
            ->withCount('followers')
            ->findOrFail($id);

        $viewer = $request->user('sanctum');
        $brand->is_following = $viewer
            ? $this->follows->isFollowing($viewer, $brand->id)
            : null;

        $products = $brand->products()
            ->active()
            ->with('category')
            ->latest('products.created_at')
            ->paginate(15);

        return ProductResource::collection($products)->additional([
            'brand' => (new BrandResource($brand))->resolve(),
        ]);
    }
}