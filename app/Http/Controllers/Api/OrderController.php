<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\HandlesIdempotency;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusHistoryResource;
use App\Services\CartService;
use App\Http\Resources\CartResource;

class OrderController extends Controller
{    use HandlesIdempotency;
 
    
  public function __construct(
    protected OrderService $orderService,
    protected PaymentService $paymentService,
    protected CartService $cartService,
) {}


       public function store(Request $request)
    {
        $data = $request->validate([
            'coupon_code'        => 'nullable|string|max:50',
            'payment_method'     => 'nullable|string|in:' . implode(',', config('store.payment_methods')),
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',  // ← ضيف السطر ده
            'items.*.quantity'   => 'required|integer|min:1|max:100',
        'shipping_method'    => 'nullable|string|in:' . implode(',', array_keys(config('store.shipping.methods', []))),  
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
    $data = $request->validate([
        'scope' => 'nullable|in:all,active,history',
    ]);

    $scope = $data['scope'] ?? 'all';

    $query = $request->user()
        ->ordersAsCustomer()
        ->with(['items', 'payment', 'shipping']);

    // Active = الطلبات في طور التنفيذ
    // History = delivered/cancelled/returned
    // all = بدون فلتر (متغطية حالة غير معروفة مستقبلًا)
    if ($scope === 'active') {
        $query->whereIn('status', [
            'new', 'confirmed', 'processing', 'ready_for_shipping', 'shipped',
        ]);
    } elseif ($scope === 'history') {
        $query->whereIn('status', [
            'delivered', 'cancelled', 'returned',
        ]);
    }

    $orders = $query->latest()->paginate(10);

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
 
 		/**
       * Timeline تتبع الأوردر (للعميل صاحب الأوردر فقط).
       */
      public function tracking(Request $request, $id)
      {
          $order = $request->user()
              ->ordersAsCustomer()
              ->with([
                  'statusHistories' => fn ($q) => $q
                      ->chronological()
                      ->with('changedBy:id,name,type'),
              ])
              ->findOrFail($id);

          return OrderStatusHistoryResource::collection($order->statusHistories);
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

 /** POST /api/orders/{id}/buy-again */
public function buyAgain(Request $request, $id)
{
    $order = $request->user()
        ->ordersAsCustomer()
        ->with('items')
        ->findOrFail($id);

    if ($order->items->isEmpty()) {
        return response()->json(['message' => 'الطلب لا يحتوي على منتجات'], 422);
    }

    $addedCount = 0;
    $skipped = [];

    foreach ($order->items as $item) {
        try {
            $this->cartService->addItem(
                $request->user(),
                (int) $item->product_id,
                (int) $item->quantity,
                $item->variant_id ? (int) $item->variant_id : null
            );
            $addedCount++;
        } catch (\DomainException $e) {
            $skipped[] = [
                'order_item_id' => $item->id,
                'product_id'    => $item->product_id,
                'product_name'  => $item->product_name,
                'variant_id'    => $item->variant_id,
                'reason'        => $e->getMessage(),
            ];
        }
    }

    $cart = $this->cartService->getCart($request->user());

    return response()->json([
        'message'       => $addedCount > 0
            ? 'تمت إضافة المنتجات المتاحة إلى السلة'
            : 'لم تتم إضافة أي منتج',
        'added_count'   => $addedCount,
        'skipped_count' => count($skipped),
        'skipped'       => $skipped,
        'cart'          => new CartResource($cart),
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
