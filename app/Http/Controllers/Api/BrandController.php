<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Http\Resources\ProductResource;
use App\Models\Brand;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::active()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30);

        return BrandResource::collection($brands);
    }

    public function show(int $id)
    {
        $brand = Brand::active()->findOrFail($id);

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