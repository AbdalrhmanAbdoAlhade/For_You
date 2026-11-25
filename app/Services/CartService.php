<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    public const MAX_QUANTITY = 100;

    public function __construct(
        protected VariantService $variants,
    ) {}

    public function getOrCreateCart(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * @throws \DomainException
     */
    public function addItem(User $user, int $productId, int $quantity = 1, ?int $variantId = null): Cart
    {
        return DB::transaction(function () use ($user, $productId, $quantity, $variantId) {

            $cart = $this->getOrCreateCart($user);

            Cart::whereKey($cart->id)->lockForUpdate()->first();

            $product = Product::find($productId);
            $variant = $this->variants->resolveForProduct($product, $variantId);

            $item = $cart->items()
                ->where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->first();

            $newQuantity = $item ? $item->quantity + $quantity : $quantity;

            if ($newQuantity > self::MAX_QUANTITY) {
                throw new \DomainException(
                    __('الحد الأقصى للكمية من المنتج الواحد هو :max', ['max' => self::MAX_QUANTITY])
                );
            }

            $this->variants->assertStockAvailable($product, $variant, $newQuantity);

            if ($item) {
                $item->update(['quantity' => $newQuantity]);
            } else {
                $cart->items()->create([
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity'   => $quantity,
                ]);
            }

            return $cart->load('items.product', 'items.variant');
        });
    }

    /**
     * @throws \DomainException
     */
    
public function updateQuantity(User $user, int $itemId, int $quantity): Cart
{
    return DB::transaction(function () use ($user, $itemId, $quantity) {
        $cart = $this->getOrCreateCart($user);

        // قفل السلة أثناء التعديل
        $cart = Cart::whereKey($cart->id)
            ->lockForUpdate()
            ->firstOrFail();

        // التأكد أن العنصر تابع لسلة المستخدم مع قفله
        $item = $cart->items()
            ->with(['product', 'variant'])
            ->whereKey($itemId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($quantity <= 0) {
            $item->delete();
        } else {
            if ($quantity > self::MAX_QUANTITY) {
                throw new \DomainException(
                    __('الكمية المطلوبة تتجاوز الحد المسموح')
                );
            }

            $variant = $this->variants->resolveForProduct(
                $item->product,
                $item->variant_id ? (int) $item->variant_id : null
            );

            $this->variants->assertStockAvailable(
                $item->product,
                $variant,
                $quantity
            );

            // حفظ الكمية الجديدة
            $item->update(['quantity' => $quantity]);
        }

        return $cart->load('items.product', 'items.variant');
    });
}

    public function removeItem(User $user, int $itemId): Cart
    {
        $cart = $this->getOrCreateCart($user);
        $cart->items()->where('id', $itemId)->delete();

        return $cart->load('items.product', 'items.variant');
    }

    public function clear(User $user): void
    {
        $cart = $this->getOrCreateCart($user);
        $cart->items()->delete();
    }

    public function getCart(User $user): Cart
    {
        return $this->getOrCreateCart($user)->load('items.product.category', 'items.variant');
    }
}