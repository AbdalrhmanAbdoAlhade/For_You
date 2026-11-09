<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PromotionalLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Notifications\OrderStatusUpdated;
use App\Notifications\NewCommission;

class OrderService
{
    public function __construct(
        protected InfluencerCommissionService $commissionService
    ) {}

    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {

            $order = Order::create([
                'order_number'        => $this->generateOrderNumber(),
                'customer_id'         => $data['customer_id'],
                'influencer_id'       => $data['influencer_id'] ?? null,
                'promotional_link_id' => $data['promotional_link_id'] ?? null,
                'status'              => 'new',
                'subtotal'            => $data['subtotal'],
                'discount'            => $data['discount'] ?? 0,
                'shipping_cost'       => $data['shipping_cost'] ?? 0,
                'total'               => $data['total'],
                'customer_name'       => $data['customer_name'],
                'customer_phone'      => $data['customer_phone'],
                'customer_email'      => $data['customer_email'] ?? null,
                'shipping_address'    => $data['shipping_address'],
                'city'                => $data['city'] ?? null,
                'country'             => $data['country'] ?? null,
                'notes'               => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                OrderItem::create([
                    'order_id'       => $order->id,
                    'product_id'     => $product->id,
                    'product_name'   => $product->name,
                    'price'          => $product->price,
                    'discount_price' => $product->discount_price,
                    'quantity'       => $item['quantity'],
                    'total'          => ($product->discount_price ?? $product->price) * $item['quantity'],
                ]);

                // تقليل المخزون
                $product->decrement('stock', $item['quantity']);
            }

            // زيادة التحويلات على الرابط الترويجي
            if (!empty($data['promotional_link_id'])) {
                PromotionalLink::where('id', $data['promotional_link_id'])
                    ->increment('conversions');
            }

            return $order->load('items');
        });
    }

  public function updateStatus(Order $order, string $status): Order
{
    $order->update(['status' => $status]);

    // إشعار للعميل
    $order->customer->notify(new OrderStatusUpdated($order));

    // لما الطلب يكتمل → نحسب عمولة المؤثر + نبعت إشعار
    if ($status === 'delivered' && $order->influencer_id) {
        $this->commissionService->calculateAndStore($order);

        // إشعار للمؤثر (بعد حساب العمولة)
        $order->influencer->notify(new NewCommission(
            $order->commissions()->latest()->first()
        ));
    }

    return $order;
}
    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . strtoupper(Str::random(8));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
