<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandFollowService;
use Illuminate\Http\Request;

class BrandFollowController extends Controller
{
    public function __construct(
        protected BrandFollowService $follows
    ) {}

    /** POST brands/{id}/follow */
    public function follow(Request $request, int $id)
    {
        $brand = Brand::active()->findOrFail($id);

        return response()->json($this->follows->follow($request->user(), $brand));
    }

    /** DELETE brands/{id}/follow */
    public function unfollow(Request $request, int $id)
    {
        $brand = Brand::findOrFail($id);

        return response()->json($this->follows->unfollow($request->user(), $brand));
    }

/** GET following/brands: كل البراندات اللي بتابعها */
public function following(Request $request)
{
    $list = $request->user()->followedBrands()
        ->where('brands.is_active', true)
        ->withCount('followers')
        ->orderBy('brands.name')
        ->paginate(20);

    // المستخدم بيتابعهم كلهم بالتعريف
    $list->getCollection()->each(fn ($brand) => $brand->is_following = true);

    return BrandResource::collection($list);
}
    
}
