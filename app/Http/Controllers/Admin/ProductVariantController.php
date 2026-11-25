<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\VariantService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    public function __construct(
        protected VariantService $variants,
    ) {}

    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $product->variants()->get(),
        ]);
    }

   
public function store(Request $request, Product $product): JsonResponse
{
    $data = $this->validateVariant($request, $product);

    try {
        $variant = \DB::transaction(function () use ($product, $data) {
            // قفل المنتج أولاً بنفس ترتيب إنشاء الطلب
            $lockedProduct = Product::whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            // إنشاء الـ Variant داخل نفس الـ Transaction
            $variant = $lockedProduct->variants()->create($data);

            // مزامنة المخزون قبل إنهاء الـ Transaction
            $this->variants->syncProductStock($lockedProduct);

            return $variant;
        });
    } catch (UniqueConstraintViolationException $e) {
        throw ValidationException::withMessages([
            'size' => __('نفس تركيبة المقاس/اللون موجودة بالفعل'),
        ]);
    }

    return response()->json([
        'data' => $variant->fresh(),
    ], 201);
}


   
public function update(
    Request $request,
    Product $product,
    ProductVariant $variant
): JsonResponse {
    if ($variant->product_id !== $product->id) {
        abort(404);
    }

    $data = $this->validateVariant($request, $product, $variant->id);

    try {
        $updatedVariant = \DB::transaction(function () use (
            $product,
            $variant,
            $data
        ) {
            // قفل المنتج أولاً
            $lockedProduct = Product::whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            // قفل الـ Variant والتأكد من تبعيته للمنتج
            $lockedVariant = ProductVariant::whereKey($variant->id)
                ->where('product_id', $lockedProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            // تحديث البيانات
            $lockedVariant->update($data);

            // مزامنة المخزون داخل نفس الـ Transaction
            $this->variants->syncProductStock($lockedProduct);

            return $lockedVariant;
        });
    } catch (UniqueConstraintViolationException $e) {
        throw ValidationException::withMessages([
            'size' => __('نفس تركيبة المقاس/اللون موجودة بالفعل'),
        ]);
    }

    return response()->json([
        'data' => $updatedVariant->fresh(),
    ]);
}


   
public function destroy(Product $product, ProductVariant $variant): JsonResponse
{
    if ($variant->product_id !== $product->id) {
        abort(404);
    }

    $deletedCartItemsCount = 0;

    try {
        $result = \DB::transaction(function () use (
            $product,
            $variant,
            &$deletedCartItemsCount
        ) {
            // نفس ترتيب الأقفال المستخدم في إنشاء الطلب:
            // Product أولاً ثم Variant
            $lockedProduct = Product::whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedVariant = ProductVariant::whereKey($variant->id)
                ->where('product_id', $lockedProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            // الفحص داخل الـ Transaction وبعد الحصول على الأقفال
            if ($lockedVariant->orderItems()->exists()) {
                return false;
            }

            // حذف عناصر السلة التابعة للـ Variant
            $deletedCartItemsCount = $lockedVariant->cartItems()->delete();

            // حذف الـ Variant ومزامنة مخزون المنتج
            $lockedVariant->delete();

            $this->variants->syncProductStock($lockedProduct);

            return true;
        });
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'message' => 'تعذر حذف الـ variant، حاول مرة أخرى',
        ], 500);
    }

    if (!$result) {
        return response()->json([
            'message' => 'الـ variant مستخدم في أوردرات سابقة، عطّله بدل الحذف',
        ], 422);
    }

    return response()->json([
        'message' => 'تم حذف الـ variant',
        'deleted_cart_items' => $deletedCartItemsCount,
    ]);
}

    /** تحويل منتج simple لـ variants — لازم مجموع الـ stock يتطابق */
    public function convert(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'variants'               => 'required|array|min:1',
            'variants.*.size'        => 'nullable|string|max:50',
            'variants.*.color'       => 'nullable|string|max:50',
            'variants.*.sku'         => 'nullable|string|max:100|distinct',
            'variants.*.stock'       => 'required|integer|min:0',
            'variants.*.price'       => 'nullable|numeric|min:0',
            'variants.*.discount_price' => 'nullable|numeric|min:0',
            'variants.*.sort_order'  => 'nullable|integer|min:0',
            'variants.*.is_active'   => 'nullable|boolean',
        ]);

        try {
            $this->variants->convertToVariants($product, $data['variants']);
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'message' => 'تم تحويل المنتج لـ variants',
            'data'    => $product->fresh()->load('variants'),
        ]);
    }

    protected function validateVariant(Request $request, Product $product, ?int $ignoreId = null): array
    {
        return $request->validate([
            'size'           => 'nullable|string|max:50',
            'color'          => 'nullable|string|max:50',
            'sku'            => ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($ignoreId)],
            'stock'          => 'required|integer|min:0',
            'price'          => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);
    }
}