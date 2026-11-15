<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\HandlesIdempotency;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{    use HandlesIdempotency;
 
    
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService
    ) {}

       public function store(Request $request)
    {
        $data = $request->validate([
            'coupon_code'        => 'nullable|string|max:50',
            'payment_method'     => 'nullable|string|in:' . implode(',', config('store.payment_methods')),
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

        return $this->idempotent($request, 'orders', function () use ($request, $data) {
            try {
                $order = $this->orderService->createOrder($request->user(), $data);
            } catch (\DomainException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json((new OrderResource($order))->resolve($request), 201);
        }, required: true);
    }
 
    public function myOrders(Request $request)
    {
        $orders = $request->user()
            ->ordersAsCustomer()
            ->with(['items', 'payment', 'shipping'])
            ->latest()
            ->paginate(10);

        // نحافظ على شكل الـ paginator الحالي مع إخفاء بيانات البوابة والعمولة.
        $orders->setCollection(
            $orders->getCollection()->map(
                fn ($order) => (new OrderResource($order))->resolve($request)
            )
        );

        return response()->json($orders);
    }

    public function show(Request $request, $id)
    {
        $order = $request->user()
            ->ordersAsCustomer()
            ->with(['items.product', 'payment', 'shipping'])
            ->findOrFail($id);

        return response()->json((new OrderResource($order))->resolve($request));
    }

        public function cancel(Request $request, $id)
    {
        try {
            $order = $this->orderService->cancelByCustomer($request->user(), (int) $id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تم إلغاء الطلب',
            'order'   => (new OrderResource($order))->resolve($request),
        ]);
    }

    /**
     * بدء/إعادة محاولة دفع الطلب عبر بوابة معينة.
     */
    public function pay(Request $request, $id)
    {
        $data = $request->validate([
            'gateway' => 'nullable|string|in:' . implode(',', array_keys((array) config('payments.gateways', []))),
        ]);

        $order = $request->user()
            ->ordersAsCustomer()
            ->findOrFail($id);

        try {
            $payment = $this->paymentService->initiate(
                $order,
                $data['gateway'] ?? null
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تم تجهيز الدفع',
            'payment' => $payment,
        ]);
    }

}
