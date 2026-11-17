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

        DB::table('products')->where('id', $product->id)->update(['stock' => $total]);
        $product->stock = $total;
    }

    /**
     * تحويل منتج من simple لـ variants.
     * إجباري: مجموع الـ stock الجديد = الـ stock الحالي للمنتج.
     *
     * @param  array<int, array{size?:?string, color?:?string, sku?:?string, stock:int, price?:?float, discount_price?:?float, sort_order?:int, is_active?:bool}>  $variants
     */
    public function convertToVariants(Product $product, array $variants, ?int $expectedTotal = null): void
    {
        if (ProductVariant::where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages([
                'variants' => ['المنتج ده عنده variants بالفعل'],
            ]);
        }

        if ($variants === []) {
            throw ValidationException::withMessages([
                'variants' => ['لازم تضيف variant واحد على الأقل'],
            ]);
        }

        $expectedTotal ??= (int) $product->stock;
        $sum = 0;

        foreach ($variants as $v) {
            $sum += (int) ($v['stock'] ?? 0);
        }

        if ($sum !== $expectedTotal) {
            throw ValidationException::withMessages([
                'variants' => [
                    "مجموع مخزون الـ variants ({$sum}) لازم يساوي مخزون المنتج الحالي ({$expectedTotal})",
                ],
            ]);
        }

        DB::transaction(function () use ($product, $variants) {
            foreach ($variants as $i => $v) {
                $product->variants()->create([
                    'size'           => $v['size'] ?? null,
                    'color'          => $v['color'] ?? null,
                    'sku'            => $v['sku'] ?? null,
                    'stock'          => (int) $v['stock'],
                    'price'          => $v['price'] ?? null,
                    'discount_price' => $v['discount_price'] ?? null,
                    'sort_order'     => $v['sort_order'] ?? $i,
                    'is_active'      => $v['is_active'] ?? true,
                ]);
            }

            $this->syncProductStock($product);
        });
    }

    /**
     * يحدد الـ variant المناسب للمنتج، مع validation كامل.
     * - منتج بدون variants → يرجّع null (بس يرفض لو variantId مبعوت)
     * - منتج بـ variants → variantId إجباري
     *
     * @throws \DomainException
     */
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

    /**
     * يتحقق من توفر الكمية في الـ stock الصح.
     *
     * @throws \DomainException
     */
    public function assertStockAvailable(?Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if (!$product) {
            throw new \DomainException(__('هذا المنتج غير متاح حالياً'));
        }

        $available = $variant ? (int) $variant->stock : (int) $product->stock;

        if ($available < $quantity) {
            throw new \DomainException(
                __('الكمية المطلوبة غير متوفرة (المتاح: :stock)', ['stock' => $available])
            );
        }
    }
}