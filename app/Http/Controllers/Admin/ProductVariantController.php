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
            $variant = $product->variants()->create($data);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'size' => __('نفس تركيبة المقاس/اللون موجودة بالفعل'),
            ]);
        }

        $this->variants->syncProductStock($product);

        return response()->json(['data' => $variant->fresh()], 201);
    }

    public function update(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        if ($variant->product_id !== $product->id) {
            abort(404);
        }

        $data = $this->validateVariant($request, $product, $variant->id);

        try {
            $variant->update($data);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'size' => __('نفس تركيبة المقاس/اللون موجودة بالفعل'),
            ]);
        }

        $this->variants->syncProductStock($product);

        return response()->json(['data' => $variant->fresh()]);
    }

   public function destroy(Product $product, ProductVariant $variant): JsonResponse
{
    if ($variant->product_id !== $product->id) {
        abort(404);
    }

    // Block deletion if variant was used in any historical order (preserve snapshots + FK integrity)
    if ($variant->orderItems()->exists()) {
        return response()->json([
            'message' => 'الـ variant مستخدم في أوردرات سابقة، عطّله بدل الحذف',
        ], 422);
    }

    $deletedCartItemsCount = 0;

    try {
        \DB::transaction(function () use ($product, $variant, &$deletedCartItemsCount) {
            // Delete dependent cart items FIRST to prevent orphans
            // (otherwise variant_id becomes NULL via nullOnDelete, leaving broken cart entries)
            $deletedCartItemsCount = $variant->cartItems()->delete();

            $variant->delete();

            $this->variants->syncProductStock($product);
        });
    } catch (\Throwable $e) {
        report($e);
        return response()->json([
            'message' => 'تعذر حذف الـ variant، حاول مرة أخرى',
        ], 500);
    }

    return response()->json([
        'message'             => 'تم حذف الـ variant',
        'deleted_cart_items'  => $deletedCartItemsCount,
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