<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    public const MAX_QUANTITY = 100;

    public function getOrCreateCart(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * @throws \DomainException
     */
    public function addItem(User $user, int $productId, int $quantity = 1): Cart
    {
        return DB::transaction(function () use ($user, $productId, $quantity) {

            $cart = $this->getOrCreateCart($user);

            // lock على سلة العميل عشان طلبين متزامنين ميكسروش الـ unique
            Cart::whereKey($cart->id)->lockForUpdate()->first();

            $product = Product::find($productId);
            $this->assertAvailable($product, $quantity);

            $item = $cart->items()->where('product_id', $productId)->first();

            if ($item) {
                $newQuantity = $item->quantity + $quantity;
                $this->assertAvailable($product, $newQuantity);

                $item->update(['quantity' => $newQuantity]);
            } else {
                $cart->items()->create([
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                ]);
            }

            return $cart->load('items.product');
        });
    }

    /**
     * @throws \DomainException
     */
    public function updateQuantity(User $user, int $itemId, int $quantity): Cart
    {
        $cart = $this->getOrCreateCart($user);
        $item = $cart->items()->with('product')->findOrFail($itemId);

        if ($quantity <= 0) {
            $item->delete();
        } else {
            $this->assertAvailable($item->product, $quantity);
            $item->update(['quantity' => $quantity]);
        }

        return $cart->load('items.product');
    }

    public function removeItem(User $user, int $itemId): Cart
    {
        $cart = $this->getOrCreateCart($user);
        $cart->items()->where('id', $itemId)->delete();

        return $cart->load('items.product');
    }

    public function clear(User $user): void
    {
        $cart = $this->getOrCreateCart($user);
        $cart->items()->delete();
    }

    public function getCart(User $user): Cart
    {
        return $this->getOrCreateCart($user)->load('items.product.category');
    }

    /**
     * @throws \DomainException
     */
    protected function assertAvailable(?Product $product, int $quantity): void
    {
        if (!$product || !$product->is_active) {
            throw new \DomainException('هذا المنتج غير متاح حالياً');
        }

        if ($quantity > self::MAX_QUANTITY) {
            throw new \DomainException('الحد الأقصى للكمية من المنتج الواحد هو ' . self::MAX_QUANTITY);
        }

        if ($product->stock < $quantity) {
            throw new \DomainException("الكمية المطلوبة غير متوفرة في المخزون (المتاح: {$product->stock})");
        }
    }
}