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
        // فلاتر عامة: لازم تتحقق قبل الاستخدام عشان array متكسرش الـ query.
        $filters = $request->validate([
            'search'      => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|exists:categories,id',
            'featured'    => 'nullable|boolean',
            'min_price'   => 'nullable|numeric|min:0',
            'max_price'   => 'nullable|numeric|min:0',
            'sort'        => 'nullable|in:price_asc,price_desc,newest,oldest',
            'per_page'    => 'nullable|integer|min:1',
        ]);

        // نحافظ على السلوك القديم: القيمة بتتقص عند 50 بدل ما ترفض الطلب.
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);
        $search = $filters['search'] ?? null;

        $products = Product::active()
            ->with('category')
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%");
                });
            })
            // featured لازم يكون boolean صريح
            ->when(
                ($filters['featured'] ?? false) === true,
                fn ($q) => $q->featured()
            )
            // فلتر السعر على السعر الفعّال (خصم لو موجود)
            ->when(isset($filters['min_price']), function ($q) use ($filters) {
                $q->whereRaw(
                    'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) >= ?',
                    [(float) $filters['min_price']]
                );
            })
            ->when(isset($filters['max_price']), function ($q) use ($filters) {
                $q->whereRaw(
                    'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) <= ?',
                    [(float) $filters['max_price']]
                );
            })
            ->when($filters['sort'] ?? null, function ($q) use ($filters) {
                match ($filters['sort']) {
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
