<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Shipping;
use App\Services\ShippingGateways\ShippingGatewayManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ShippingService
{
    /** انتقالات حالة الشحنة المسموحة (ممنوع الرجوع الحر لـ pending) */
    protected const TRANSITIONS = [
        'pending'          => ['picked_up', 'failed'],
        'picked_up'        => ['in_transit', 'failed', 'returned'],
        'in_transit'       => ['out_for_delivery', 'failed', 'returned'],
        'out_for_delivery' => ['delivered', 'failed', 'returned'],
        'delivered'        => ['returned'],
        'failed'           => ['pending', 'returned'],
        'returned'         => [],
    ];

    public function __construct(
        protected OrderService $orderService,
        protected ShippingGatewayManager $gateways
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

        $driver = $this->gateways->driver();
        $result = $driver->createShipment($order, $data);

        try {
            return DB::transaction(function () use ($order, $data, $result) {
                // قفل الطلب لمنع إنشاء شحنتين متزامنتين لنفس الطلب.
                Order::whereKey($order->id)->lockForUpdate()->first();

                if (Shipping::where('order_id', $order->id)->exists()) {
                    throw new \DomainException('للطلب شحنة بالفعل');
                }

                return Shipping::create([
                    'order_id'         => $order->id,
                    'shipping_company' => $result['shipping_company'],
                    'tracking_number'  => $result['tracking_number'],
                    'shipping_address' => $data['shipping_address'] ?? $order->shipping_address,
                    'status'           => $result['status'],
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw new \DomainException('للطلب شحنة بالفعل');
        }
    }

    /**
     * بيحدث الشحنة وبيزامن حالة الطلب معاها (لو الانتقال مسموح).
     */
    public function updateStatus(Shipping $shipping, string $status): Shipping
    {
        return DB::transaction(function () use ($shipping, $status) {

            $shipping = Shipping::with('order')
                ->whereKey($shipping->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($shipping->status === $status) {
                return $shipping;
            }

            $this->assertTransitionAllowed($shipping->status, $status);

            $updates = ['status' => $status];

            if (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered'], true)
                && !$shipping->shipped_at) {
                $updates['shipped_at'] = now();
            }

            if ($status === 'delivered') {
                $updates['delivered_at'] = now();
            }

            $shipping->update($updates);

            $this->syncOrderStatus($shipping->fresh('order'), $status);

            return $shipping->fresh();
        });
    }

    /**
     * Hook لـ webhook الشحن: يحدّث الحالة من بوابة خارجية بنفس قواعد الانتقال.
     *
     * @throws \DomainException
     */
    public function applyWebhookStatus(Shipping $shipping, string $status): Shipping
    {
        return $this->updateStatus($shipping, $status);
    }

    /** @throws \DomainException */
    protected function assertTransitionAllowed(string $from, string $to): void
    {
        if (in_array($from, ['returned'], true)) {
            throw new \DomainException('لا يمكن تغيير حالة شحنة مرتجعة');
        }

        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \DomainException(
                __('لا يمكن نقل الشحنة من الحالة (:from) إلى (:to)', [
                    'from' => $from,
                    'to'   => $to,
                ])
            );
        }
    }

    protected function syncOrderStatus(Shipping $shipping, string $status): void
    {
        $order = $shipping->order;

        if (!$order) {
            return;
        }

        $target = match ($status) {
            'picked_up', 'in_transit', 'out_for_delivery' => 'shipped',
            'delivered'                                   => 'delivered',
            'returned'                                    => 'returned',
            default                                       => null,
        };

        if (!$target) {
            return;
        }

        /*
         * إرجاع شحنة لطلب مدفوع: OrderService بيرفض تحويل الطلب لـ returned
         * قبل استرداد الدفعة. هنا بنسمح بتحديث الشحنة نفسها ونسجل الحالة
         * للمراجعة اليدوية بدل ما نرجّع 422 للبوابة وتفضل تعيد الإرسال.
         */
        if ($target === 'returned'
            && $order->payment()->where('status', 'paid')->exists()) {
            AuditLog::record('shipping.returned.needs_refund', $order, [
                'shipping_id' => $shipping->id,
                'status'      => $status,
                'needs_refund' => true,
            ]);

            return;
        }

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
}
