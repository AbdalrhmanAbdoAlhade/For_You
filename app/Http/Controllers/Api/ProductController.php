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
        $products = Product::active()
            ->with('category')
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            })
            ->when($request->featured, fn($q) => $q->featured())
            ->when($request->min_price, fn($q) => $q->where('price', '>=', $request->min_price))
            ->when($request->max_price, fn($q) => $q->where('price', '<=', $request->max_price))
            ->when($request->sort, function ($q) use ($request) {
                match ($request->sort) {
                    'price_asc'  => $q->orderBy('price', 'asc'),
                    'price_desc' => $q->orderBy('price', 'desc'),
                    'newest'     => $q->latest(),
                    'oldest'     => $q->oldest(),
                    default      => $q->latest(),
                };
            })
            ->paginate($request->get('per_page', 15));

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
