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
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'required|integer|min:1|max:' . CartService::MAX_QUANTITY,
        ]);

        try {
            $cart = $this->cartService->addItem(
                $request->user(),
                $data['product_id'],
                $data['quantity']
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new CartResource($cart))->response()->setStatusCode(201);
    }

    public function update(Request $request, $itemId)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:0|max:' . CartService::MAX_QUANTITY,
        ]);

        try {
            $cart = $this->cartService->updateQuantity(
                $request->user(),
                (int) $itemId,
                $data['quantity']
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CartResource($cart);
    }

    public function destroy(Request $request, $itemId)
    {
        $cart = $this->cartService->removeItem($request->user(), (int) $itemId);
        return new CartResource($cart);
    }

    public function clear(Request $request)
    {
        $this->cartService->clear($request->user());
        return response()->json(['message' => 'تم تفريغ السلة بنجاح']);
    }
}