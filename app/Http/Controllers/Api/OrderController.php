<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PromotionalLinkService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PromotionalLinkService $linkService
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.quantity'      => 'required|integer|min:1',
            'customer_name'         => 'required|string',
            'customer_phone'        => 'required|string',
            'customer_email'        => 'nullable|email',
            'shipping_address'      => 'required|string',
            'city'                  => 'nullable|string',
            'country'               => 'nullable|string',
            'notes'                 => 'nullable|string',
            'ref'                   => 'nullable|string', // كود الرابط الترويجي
        ]);

        $influencerId = null;
        $promotionalLinkId = null;

        if (!empty($data['ref'])) {
            $link = $this->linkService->trackClick($data['ref']);
            if ($link) {
                $influencerId = $link->influencer_id;
                $promotionalLinkId = $link->id;
            }
        }

        // حساب المجموع (مبسط - يفضل يكون في Service)
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $product = \App\Models\Product::find($item['product_id']);
            $subtotal += ($product->discount_price ?? $product->price) * $item['quantity'];
        }

        $orderData = [
            'customer_id'         => $request->user()->id,
            'influencer_id'       => $influencerId,
            'promotional_link_id' => $promotionalLinkId,
            'subtotal'            => $subtotal,
            'discount'            => 0,
            'shipping_cost'       => 0,
            'total'               => $subtotal,
            'customer_name'       => $data['customer_name'],
            'customer_phone'      => $data['customer_phone'],
            'customer_email'      => $data['customer_email'] ?? null,
            'shipping_address'    => $data['shipping_address'],
            'city'                => $data['city'] ?? null,
            'country'             => $data['country'] ?? null,
            'notes'               => $data['notes'] ?? null,
            'items'               => $data['items'],
        ];

        $order = $this->orderService->createOrder($orderData);

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
