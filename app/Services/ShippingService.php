<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Support\Facades\DB;

class ShippingService
{
    public function __construct(
        protected OrderService $orderService
    ) {}

       public function createForOrder(Order $order, array $data): Shipping
    {
        if (Shipping::where('order_id', $order->id)->exists()) {
            throw new \DomainException('للطلب شحنة بالفعل');
        }

        // الشحنة بتتعمل بس لطلب جاهز للشحن (أو اتشحن يدويًا قبلها)
        if (!in_array($order->status, ['ready_for_shipping', 'shipped'], true)) {
            throw new \DomainException('لا يمكن إنشاء شحنة إلا لطلب جاهز للشحن');
        }

        if (config('store.require_payment_before_shipping')
            && !$order->payment()->where('status', 'paid')->exists()) {
            throw new \DomainException('لازم يتأكد الدفع قبل إنشاء الشحنة');
        }

        return Shipping::create([
            'order_id'         => $order->id,
            'shipping_company' => $data['shipping_company'],
            'tracking_number'  => $data['tracking_number'] ?? null,
            'shipping_address' => $data['shipping_address'] ?? $order->shipping_address,
            'status'           => 'pending',
        ]);
    }

    /**
     * بيحدث الشحنة وبيزامن حالة الطلب معاها (لو الانتقال مسموح).
     */
    public function updateStatus(Shipping $shipping, string $status): Shipping
    {
        return DB::transaction(function () use ($shipping, $status) {

            $shipping = Shipping::with('order')->whereKey($shipping->id)->lockForUpdate()->firstOrFail();

            $updates = ['status' => $status];

            if (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered'], true)
                && !$shipping->shipped_at) {
                $updates['shipped_at'] = now();
            }

            if ($status === 'delivered') {
                $updates['delivered_at'] = now();
            }

            $shipping->update($updates);

            $target = match ($status) {
                'picked_up', 'in_transit', 'out_for_delivery' => 'shipped',
                'delivered'                                   => 'delivered',
                'returned'                                    => 'returned',
                default                                       => null,
            };

            if ($target) {
                $order = $shipping->order;

                // التسليم لازم يعدّي على "shipped" لو الطلب لسه جاهز للشحن
                if ($target === 'delivered'
                    && $order->status === 'ready_for_shipping'
                    && $this->orderService->canTransition($order->status, 'shipped')) {
                    $order = $this->orderService->updateStatus($order, 'shipped');
                }

                if ($this->orderService->canTransition($order->status, $target)) {
                    $this->orderService->updateStatus($order, $target);
                }
            }

            return $shipping->fresh();
        });
    }

    /**
     * بيحدث الشحنة وبيزامن حالة الطلب معاها (لو الانتقال مسموح).
     */
    public function updateStatus(Shipping $shipping, string $status): Shipping
    {
        return DB::transaction(function () use ($shipping, $status) {

            $shipping = Shipping::with('order')->whereKey($shipping->id)->lockForUpdate()->firstOrFail();

            $updates = ['status' => $status];

            if (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered'], true)
                && !$shipping->shipped_at) {
                $updates['shipped_at'] = now();
            }

            if ($status === 'delivered') {
                $updates['delivered_at'] = now();
            }

            $shipping->update($updates);

            $target = match ($status) {
                'picked_up', 'in_transit', 'out_for_delivery' => 'shipped',
                'delivered'                                   => 'delivered',
                'returned'                                    => 'returned',
                default                                       => null,
            };

            if ($target && $this->orderService->canTransition($shipping->order->status, $target)) {
                $this->orderService->updateStatus($shipping->order, $target);
            }

            return $shipping->fresh();
        });
    }
}
