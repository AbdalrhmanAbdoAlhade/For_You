<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->get('per_page', 15), 1), 50);

        $products = Product::active()
            ->with('category')
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('description', 'like', "%{$request->search}%")
                        ->orWhere('name_en', 'like', "%{$request->search}%");
                });
            })
            // featured لازم يكون boolean صريح
            ->when(
                $request->has('featured') && $request->boolean('featured'),
                fn ($q) => $q->featured()
            )
            // فلتر السعر على السعر الفعّال (خصم لو موجود)
            ->when($request->min_price, function ($q) use ($request) {
                $min = (float) $request->min_price;
                $q->whereRaw(
                    'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) >= ?',
                    [$min]
                );
            })
            ->when($request->max_price, function ($q) use ($request) {
                $max = (float) $request->max_price;
                $q->whereRaw(
                    'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) <= ?',
                    [$max]
                );
            })
            ->when($request->sort, function ($q) use ($request) {
                match ($request->sort) {
                    'price_asc'  => $q->orderByRaw(
                        'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) ASC'
                    ),
                    'price_desc' => $q->orderByRaw(
                        'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) DESC'
                    ),
                    'newest'     => $q->latest(),
                    'oldest'     => $q->oldest(),
                    default      => $q->latest(),
                };
            }, function ($q) {
                // ترتيب افتراضي ثابت عشان الـ pagination ما يتحركش
                $q->latest();
            })
            ->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function show($id)
    {
        $product = Product::active()
            ->with('category')
            ->findOrFail($id);

        return new ProductResource($product);
    }

    public function featured()
    {
        $products = Product::active()
            ->featured()
            ->with('category')
            ->latest()
            ->take(12)
            ->get();

        return ProductResource::collection($products);
    }
}
