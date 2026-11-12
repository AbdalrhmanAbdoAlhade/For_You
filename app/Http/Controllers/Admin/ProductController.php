<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    public function index(Request $request)
    {
        $products = Product::with('category')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate(20);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'       => 'required|exists:categories,id',
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'short_description' => 'nullable|string',
            'price'             => 'required|numeric|min:0.01',
            'discount_price'    => 'nullable|numeric|gt:0',
            'stock'             => 'required|integer|min:0',
            'sku'               => 'nullable|string|unique:products,sku',
            'images'            => 'nullable|array',
            'images.*'          => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_featured'       => 'boolean',
            'is_active'         => 'boolean',
        ]);

        // الخصم لازم يكون أقل من السعر
        if (
            array_key_exists('discount_price', $data)
            && $data['discount_price'] !== null
            && (float) $data['discount_price'] >= (float) $data['price']
        ) {
            throw ValidationException::withMessages([
                'discount_price' => 'سعر الخصم يجب أن يكون أكبر من صفر وأقل من السعر الأساسي.',
            ]);
        }

        $data['slug'] = Str::slug($data['name']);

        if ($request->hasFile('images')) {
            $data['images'] = $this->fileUploadService->uploadMultiple(
                $request->file('images'),
                'products'
            );
        }

        $product = Product::create($data);

        return new ProductResource($product->load('category'));
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
            'price'             => 'sometimes|numeric|min:0.01',
            'discount_price'    => 'nullable|numeric|gt:0',
            'stock'             => 'sometimes|integer|min:0',
            'sku'               => 'nullable|string|unique:products,sku,' . $product->id,
            'images'            => 'nullable|array',
            'images.*'          => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_images'     => 'nullable|array',
            'remove_images.*'   => 'string',
            'is_featured'       => 'boolean',
            'is_active'         => 'boolean',
        ]);

        // مقارنة الخصم بالسعر الفعّال (الجديد لو اتبعت، أو القديم)
        $effectivePrice = (float) ($data['price'] ?? $product->price);

        if (
            array_key_exists('discount_price', $data)
            && $data['discount_price'] !== null
            && (float) $data['discount_price'] >= $effectivePrice
        ) {
            throw ValidationException::withMessages([
                'discount_price' => 'سعر الخصم يجب أن يكون أكبر من صفر وأقل من السعر الأساسي.',
            ]);
        }

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // الصور: مسح المطلوب + رفع الجديد، مع الإبقاء على الباقي
        $current = $product->images ?? [];

        $toRemove = array_intersect($request->input('remove_images', []), $current);
        foreach ($toRemove as $path) {
            $this->fileUploadService->delete($path);
        }
        $current = array_values(array_diff($current, $toRemove));

        if ($request->hasFile('images')) {
            $current = array_merge(
                $current,
                $this->fileUploadService->uploadMultiple($request->file('images'), 'products')
            );
        }

        unset($data['images'], $data['remove_images']);

        if ($request->hasFile('images') || !empty($toRemove)) {
            $data['images'] = $current;
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
