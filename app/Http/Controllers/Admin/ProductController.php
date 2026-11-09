<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\FileUploadService;



class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('category')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate(20);

        return response()->json($products);
    }

public function __construct(
    protected FileUploadService $fileUploadService
) {}

public function store(Request $request)
{
    $data = $request->validate([
        'category_id'       => 'required|exists:categories,id',
        'name'              => 'required|string|max:255',
        'description'       => 'nullable|string',
        'short_description' => 'nullable|string',
        'price'             => 'required|numeric|min:0',
        'discount_price'    => 'nullable|numeric|min:0',
        'stock'             => 'required|integer|min:0',
        'sku'               => 'nullable|string|unique:products,sku',
        'images'            => 'nullable|array',
        'images.*'          => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        'is_featured'       => 'boolean',
        'is_active'         => 'boolean',
    ]);

    $data['slug'] = \Illuminate\Support\Str::slug($data['name']);

    if ($request->hasFile('images')) {
        $data['images'] = $this->fileUploadService->uploadMultiple(
            $request->file('images'),
            'products'
        );
    }

    $product = Product::create($data);

    return new \App\Http\Resources\ProductResource($product->load('category'));
}

    public function show(Product $product)
    {
        return response()->json($product->load('category', 'influencers'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'category_id'       => 'sometimes|exists:categories,id',
            'name'              => 'sometimes|string|max:255',
            'description'       => 'nullable|string',
            'short_description' => 'nullable|string',
            'price'             => 'sometimes|numeric|min:0',
            'discount_price'    => 'nullable|numeric|min:0',
            'stock'             => 'sometimes|integer|min:0',
            'sku'               => 'nullable|string|unique:products,sku,' . $product->id,
            'images'            => 'nullable|array',
            'is_featured'       => 'boolean',
            'is_active'         => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $product->update($data);

        return response()->json($product->load('category'));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'تم حذف المنتج بنجاح']);
    }
}
