<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\PreviewImageService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user();
        $followed = $viewer ? $viewer->followedBrands()->pluck('brand_id')->toArray() : [];

        $brands = Brand::where('is_active', 1)
            ->orderBy('sort_order')
            ->paginate(20);

        $brandOfWeek = Brand::where('is_active', 1)
            ->where('is_brand_of_week', 1)
            ->first();

        $brands->getCollection()->transform(function ($brand) use ($viewer, $followed) {
            $brand->preview_images = PreviewImageService::getBrandPreviewImages($brand->id, $brand->logo);
            $brand->is_following = $viewer ? in_array($brand->id, $followed) : null;
            return $brand;
        });

        $response = BrandResource::collection($brands);

        if ($brandOfWeek) {
            $brandOfWeek->preview_images = PreviewImageService::getBrandPreviewImages($brandOfWeek->id, $brandOfWeek->logo);
            $brandOfWeek->is_following = $viewer ? in_array($brandOfWeek->id, $followed) : null;
            
            $response->additional([
                'brand_of_week' => new BrandResource($brandOfWeek),
            ]);
        }

        return $response;
    }

    public function show($slug)
    {
        // البحث بالـ Slug حصراً لضمان عمل الـ SEO
        $brand = Brand::where('slug', $slug)->where('is_active', 1)->firstOrFail();
        
        $brand->preview_images = PreviewImageService::getBrandPreviewImages($brand->id, $brand->logo);
        
        $viewer = request()->user();
        $followed = $viewer ? $viewer->followedBrands()->pluck('brand_id')->toArray() : [];
        $brand->is_following = $viewer ? in_array($brand->id, $followed) : null;

        return new BrandResource($brand);
    }
}
