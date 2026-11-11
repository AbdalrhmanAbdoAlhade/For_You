<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected CartService $cartService
    ) {}

    /** يحسب الإجمالي والشحن والخصم من غير ما ينشئ طلب */
  public function quote(Request $request)
{
    $data = $request->validate([
        'address_id'  => 'nullable|integer',
        'coupon_code' => 'nullable|string|max:50',
        'city'        => 'nullable|string|max:100',
        'ref'         => 'nullable|string|max:32',
    ]);

    // تحديد المدينة من العنوان المحفوظ إذا تم إرسال address_id
    if (!empty($data['address_id'])) {
        $data['city'] = $request->user()
            ->addresses()
            ->findOrFail($data['address_id'])
            ->city;
    }

    $data['items'] = $this->cartItems($request);

    if (empty($data['items'])) {
        return response()->json([
            'message' => 'السلة فارغة',
        ], 422);
    }

    try {
        $quote = $this->orderService->quote(
            $request->user(),
            $data
        );

        if (!empty($data['ref'])) {
            $quote['ref_applied'] = $this->orderService->isRefApplicable(
                $request->user(),
                $data['ref'],
                $data['items']
            );
        }

        return response()->json($quote);

    } catch (\DomainException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 422);
    }
}
    /** ينشئ الطلب من السلة ويفضيها */
    public function store(Request $request)
    {
        $data = $request->validate([
            'address_id'       => 'nullable|integer',
            'customer_name'    => 'required_without:address_id|nullable|string|max:255',
            'customer_phone'   => 'required_without:address_id|nullable|string|max:30',
            'customer_email'   => 'nullable|email',
            'shipping_address' => 'required_without:address_id|nullable|string',
            'city'             => 'nullable|string|max:100',
            'country'          => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
            'coupon_code'      => 'nullable|string|max:50',
            'ref'              => 'nullable|string|max:32',
            'payment_method'   => 'nullable|string|in:' . implode(',', config('store.payment_methods')),
        ]);

        // لو العميل اختار عنوان محفوظ، بياناته بتحل محل الحقول اليدوية
        if (!empty($data['address_id'])) {
            $address = $request->user()->addresses()->findOrFail($data['address_id']);

            $data['customer_name']    = $address->recipient_name;
            $data['customer_phone']   = $address->phone;
            $data['shipping_address'] = $address->address_line;
            $data['city']             = $address->city;
            $data['country']          = $address->country;
        }

        $data['items'] = $this->cartItems($request);

        if (empty($data['items'])) {
            return response()->json(['message' => 'السلة فارغة'], 422);
        }

        try {
            $order = DB::transaction(function () use ($request, $data) {
                $order = $this->orderService->createOrder($request->user(), $data);
                $this->cartService->clear($request->user());

                return $order;
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(
    $order->toArray() + ['ref_applied' => $order->promotional_link_id !== null],
    201
);
    }

    protected function cartItems(Request $request): array
    {
        return $this->cartService->getCart($request->user())
            ->items
            ->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity])
            ->values()
            ->all();
    }
}
