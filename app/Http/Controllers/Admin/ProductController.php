<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\AuditLog;
use App\Models\Product;
use App\Services\FileUploadService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'search'      => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|exists:categories,id',
            'is_active'   => 'nullable|boolean',
        ]);

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
            'category_id'          => 'required|exists:categories,id',
            'name'                 => 'required|string|max:255',
            'name_en'              => 'nullable|string|max:255',
            'description'          => 'nullable|string',
            'description_en'       => 'nullable|string',
            'short_description'    => 'nullable|string',
            'short_description_en' => 'nullable|string',
            'slug'                 => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')],
            'price'                => 'required|numeric|min:0.01',
            'discount_price'       => 'nullable|numeric|gt:0',
            'stock'                => 'required|integer|min:0',
            'sku'                  => 'nullable|string|unique:products,sku',
            'images'               => 'nullable|array',
            'images.*'             => 'image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=4096,max_height=4096',
            'is_featured'          => 'boolean',
            'is_active'            => 'boolean',
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

        $data['slug'] = !empty($data['slug'])
            ? Str::slug($data['slug'])
            : $this->uniqueSlug($data['name']);

        $uploaded = [];

        try {
            if ($request->hasFile('images')) {
                $uploaded = $this->fileUploadService->uploadMultiple(
                    $request->file('images'),
                    'products'
                );
                $data['images'] = $uploaded;
            }

            $product = Product::create($data);
        } catch (UniqueConstraintViolationException $e) {
            // سباق على نفس الـ slug بين طلبين متزامنين.
            foreach ($uploaded as $path) {
                $this->fileUploadService->delete($path);
            }

            throw ValidationException::withMessages([
                'slug' => __('الرابط المختصر (slug) مستخدم بالفعل'),
            ]);
        } catch (\Throwable $e) {
            // فشل حفظ المنتج لا يترك صورًا يتيمة على القرص.
            foreach ($uploaded as $path) {
                $this->fileUploadService->delete($path);
            }

            throw $e;
        }

        AuditLog::record('product.created', $product, ['name' => $product->name]);
        HomeController::flush();

        return new ProductResource($product->load('category'));
    }

    public function show(Product $product)
    {
        return response()->json($product->load('category', 'influencers'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'category_id'          => 'sometimes|exists:categories,id',
            'name'                 => 'sometimes|string|max:255',
            'name_en'              => 'nullable|string|max:255',
            'description'          => 'nullable|string',
            'description_en'       => 'nullable|string',
            'short_description'    => 'nullable|string',
            'short_description_en' => 'nullable|string',
            'slug'                 => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($product->id)],
            'price'                => 'sometimes|numeric|min:0.01',
            'discount_price'       => 'nullable|numeric|gt:0',
            'stock'                => 'sometimes|integer|min:0',
            'sku'                  => 'nullable|string|unique:products,sku,' . $product->id,
            'images'               => 'nullable|array',
            'images.*'             => 'image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=4096,max_height=4096',
            'remove_images'        => 'nullable|array',
            'remove_images.*'      => 'string',
            'is_featured'          => 'boolean',
            'is_active'            => 'boolean',
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

        /*
         * الـ slug بيفضل ثابت لو الاسم اتغيّر، عشان روابط SEO ما تتكسرش.
         * تغييره يتم فقط لو الأدمن بعت slug صريح.
         */
        if (!empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        } else {
            unset($data['slug']);
        }

        // الصور: مسح المطلوب + رفع الجديد، مع الإبقاء على الباقي
        $current = $product->images ?? [];

        $toRemove = array_values(array_intersect($request->input('remove_images', []), $current));
        $current = array_values(array_diff($current, $toRemove));

        $uploaded = [];

        if ($request->hasFile('images')) {
            $uploaded = $this->fileUploadService->uploadMultiple($request->file('images'), 'products');
            $current = array_merge($current, $uploaded);
        }

        unset($data['images'], $data['remove_images']);

        if ($request->hasFile('images') || !empty($toRemove)) {
            $data['images'] = $current;
        }

        try {
            $product->update($data);
        } catch (UniqueConstraintViolationException $e) {
            foreach ($uploaded as $path) {
                $this->fileUploadService->delete($path);
            }

            throw ValidationException::withMessages([
                'slug' => __('الرابط المختصر (slug) مستخدم بالفعل'),
            ]);
        } catch (\Throwable $e) {
            // فشل التحديث: نحذف الصور الجديدة فقط ونبقي القديمة.
            foreach ($uploaded as $path) {
                $this->fileUploadService->delete($path);
            }

            throw $e;
        }

        // الحذف من القرص بعد نجاح التحديث فقط.
        foreach ($toRemove as $path) {
            $this->fileUploadService->delete($path);
        }

        AuditLog::record('product.updated', $product, ['name' => $product->name]);
        HomeController::flush();

        return response()->json($product->load('category'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        AuditLog::record('product.deleted', $product, ['name' => $product->name]);
        HomeController::flush();

        return response()->json(['message' => 'تم حذف المنتج بنجاح']);
    }

    /**
     * slug فريد مع تجاهل السجلات المحذوفة soft-delete (زي Category).
     */
    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'product-' . Str::random(8);
        }

        $slug = $base;
        $i = 1;

        while (
            Product::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
