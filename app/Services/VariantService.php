<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VariantService
{
    /**
     * يزامن products.stock مع مجموع الـ variants النشطة.
     * - منتج بدون variants: نسيب الـ stock زي ما هو
     * - منتج عنده variants: stock = sum(active variants.stock)
     */
    public function syncProductStock(Product $product): void
    {
        $hasVariants = ProductVariant::where('product_id', $product->id)->exists();

        if (!$hasVariants) {
            return;
        }

        $total = (int) ProductVariant::where('product_id', $product->id)
            ->where('is_active', true)
            ->sum('stock');

        DB::table('products')
            ->where('id', $product->id)
            ->update(['stock' => $total]);

        $product->stock = $total;
    }

    /**
     * تحويل منتج من simple إلى variants.
     * مجموع مخزون الـ variants النشطة فقط يجب أن يساوي مخزون المنتج الحالي.
     */
    public function convertToVariants(
        Product $product,
        array $variants,
        ?int $expectedTotal = null
    ): void {
        if ($variants === []) {
            throw ValidationException::withMessages([
                'variants' => ['لازم تضيف variant واحد على الأقل'],
            ]);
        }

        DB::transaction(function () use ($product, $variants, $expectedTotal) {
            $lockedProduct = Product::whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (ProductVariant::where('product_id', $lockedProduct->id)->exists()) {
                throw ValidationException::withMessages([
                    'variants' => ['المنتج ده عنده variants بالفعل'],
                ]);
            }

            $expectedTotal ??= (int) $lockedProduct->stock;

            $activeSum = 0;

            foreach ($variants as $variant) {
                if ($variant['is_active'] ?? true) {
                    $activeSum += (int) ($variant['stock'] ?? 0);
                }
            }

            if ($activeSum !== $expectedTotal) {
                throw ValidationException::withMessages([
                    'variants' => [
                        "مجموع مخزون الـ variants النشطة ({$activeSum}) لازم يساوي مخزون المنتج الحالي ({$expectedTotal})",
                    ],
                ]);
            }

            foreach ($variants as $i => $variant) {
                $lockedProduct->variants()->create([
                    'size'           => $variant['size'] ?? null,
                    'color'          => $variant['color'] ?? null,
                    'sku'            => $variant['sku'] ?? null,
                    'stock'          => (int) $variant['stock'],
                    'price'          => $variant['price'] ?? null,
                    'discount_price' => $variant['discount_price'] ?? null,
                    'sort_order'     => $variant['sort_order'] ?? $i,
                    'is_active'      => $variant['is_active'] ?? true,
                ]);
            }

            $this->syncProductStock($lockedProduct);
        });
    }

    public function resolveForProduct(?Product $product, ?int $variantId): ?ProductVariant
    {
        if (!$product || !$product->is_active) {
            throw new \DomainException(__('هذا المنتج غير متاح حالياً'));
        }

        $hasVariants = ProductVariant::where('product_id', $product->id)->exists();

        if (!$hasVariants) {
            if ($variantId !== null) {
                throw new \DomainException(__('الـ variant المختار لا يتبع هذا المنتج'));
            }

            return null;
        }

        if ($variantId === null) {
            throw new \DomainException(__('لازم تختار المقاس أو اللون قبل الإضافة'));
        }

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('id', $variantId)
            ->first();

        if (!$variant) {
            throw new \DomainException(__('الـ variant المختار غير موجود لهذا المنتج'));
        }

        if (!$variant->is_active) {
            throw new \DomainException(__('الـ variant المختار غير متاح'));
        }

        return $variant;
    }

    public function assertStockAvailable(
        ?Product $product,
        ?ProductVariant $variant,
        int $quantity
    ): void {
        if (!$product) {
            throw new \DomainException(__('هذا المنتج غير متاح حالياً'));
        }

        $available = $variant
            ? (int) $variant->stock
            : (int) $product->stock;

        if ($available < $quantity) {
            throw new \DomainException(
                __('الكمية المطلوبة غير متوفرة (المتاح: :stock)', [
                    'stock' => $available,
                ])
            );
        }
    }
}
