<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function index(Request $request)
    {
        $cart = $this->cartService->getCart($request->user());
        return new CartResource($cart);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        try {
            $cart = $this->cartService->addItem(
                $request->user(),
                $data['product_id'],
                $data['quantity']
            );

            return (new CartResource($cart))->response()->setStatusCode(201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $itemId)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        try {
            $cart = $this->cartService->updateQuantity(
                $request->user(),
                $itemId,
                $data['quantity']
            );

            return new CartResource($cart);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, $itemId)
    {
        $cart = $this->cartService->removeItem($request->user(), $itemId);
        return new CartResource($cart);
    }

    public function clear(Request $request)
    {
        $this->cartService->clear($request->user());
        return response()->json(['message' => 'تم تفريغ السلة بنجاح']);
    }
}
