<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1|max:100',
            'customer_name'      => 'required|string|max:255',
            'customer_phone'     => 'required|string|max:30',
            'customer_email'     => 'nullable|email',
            'shipping_address'   => 'required|string',
            'city'               => 'nullable|string|max:100',
            'country'            => 'nullable|string|max:100',
            'notes'              => 'nullable|string',
            'ref'                => 'nullable|string|max:32', // كود الرابط الترويجي
        ]);

        try {
            $order = $this->orderService->createOrder($request->user(), $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order, 201);
    }

    public function myOrders(Request $request)
    {
        $orders = $request->user()
            ->ordersAsCustomer()
            ->with(['items', 'payment', 'shipping'])
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    public function show(Request $request, $id)
    {
        $order = $request->user()
            ->ordersAsCustomer()
            ->with(['items.product', 'payment', 'shipping'])
            ->findOrFail($id);

        return response()->json($order);
    }
}
