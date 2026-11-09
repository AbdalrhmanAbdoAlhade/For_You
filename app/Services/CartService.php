<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getOrCreateCart(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function addItem(User $user, int $productId, int $quantity = 1): Cart
    {
        $product = Product::active()->findOrFail($productId);

        if ($product->stock < $quantity) {
            throw new \Exception('الكمية المطلوبة غير متوفرة في المخزون');
        }

        $cart = $this->getOrCreateCart($user);

        $item = $cart->items()->where('product_id', $productId)->first();

        if ($item) {
            $newQuantity = $item->quantity + $quantity;

            if ($product->stock < $newQuantity) {
                throw new \Exception('الكمية المطلوبة غير متوفرة في المخزون');
            }

            $item->update(['quantity' => $newQuantity]);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'quantity'   => $quantity,
            ]);
        }

        return $cart->load('items.product');
    }

    public function updateQuantity(User $user, int $itemId, int $quantity): Cart
    {
        $cart = $this->getOrCreateCart($user);
        $item = $cart->items()->findOrFail($itemId);

        if ($quantity <= 0) {
            $item->delete();
        } else {
            if ($item->product->stock < $quantity) {
                throw new \Exception('الكمية المطلوبة غير متوفرة في المخزون');
            }
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
}
