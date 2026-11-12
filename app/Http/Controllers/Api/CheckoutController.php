<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        if (!empty($data['address_id'])) {
            $data['city'] = $request->user()
                ->addresses()
                ->findOrFail($data['address_id'])
                ->city;
        }

        $data['items'] = $this->cartItems($request);

        if (empty($data['items'])) {
            return response()->json(['message' => 'السلة فارغة'], 422);
        }

        try {
            $quote = $this->orderService->quote($request->user(), $data);

            if (!empty($data['ref'])) {
                $quote['ref_applied'] = $this->orderService->isRefApplicable(
                    $request->user(),
                    $data['ref'],
                    $data['items']
                );
            }

            return response()->json($quote);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** ينشئ الطلب من السلة ويفضيها — محمي ضد التكرار */
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

        if (!empty($data['address_id'])) {
            $address = $request->user()->addresses()->findOrFail($data['address_id']);

            $data['customer_name']    = $address->recipient_name;
            $data['customer_phone']   = $address->phone;
            $data['shipping_address'] = $address->address_line;
            $data['city']             = $address->city;
            $data['country']          = $address->country;
        }

        // ===== طبقة 1: Idempotency-Key =====
        // الضغط المزدوج من نفس الجهاز بين نفس النتيجة بدل ما ينشئ طلبين
        $lock = null;
        $idemCacheKey = null;

        if ($idemKey = $request->header('Idempotency-Key')) {
            $idemCacheKey = 'checkout:' . $request->user()->id . ':' . hash('sha256', $idemKey);

            if ($cached = Cache::get($idemCacheKey)) {
                return response()->json($cached, 200); // نفس نتيجة أول محاولة
            }

            $lock = Cache::lock($idemCacheKey . ':lock', 30);
            if (!$lock->get()) {
                // نفس المفتاح قيد المعالجة — العميل يعيد المحاولة وياخد النتيجة المخزنة
                return response()->json(['message' => 'الطلب قيد المعالجة، حاول بعد ثوانٍ'], 409);
            }
        }

        try {
            // ===== طبقة 2: قفل السلة جوه الترانزاكشن =====
            // أول طلب بيقفل صف السلة، والتاني بيستنى لحد الـ commit وبعدين يلاقيها فاضية
            $order = DB::transaction(function () use ($request, $data) {
                $data['items'] = $this->lockedCartItems($request);

                if (empty($data['items'])) {
                    throw new \DomainException('السلة فارغة');
                }

                $order = $this->orderService->createOrder($request->user(), $data);
                $this->cartService->clear($request->user());

                return $order;
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            $lock?->release();
        }

        $payload = $order->toArray()
            + ['ref_applied' => $order->promotional_link_id !== null];

        if ($idemCacheKey) {
            Cache::put($idemCacheKey, $payload, now()->addDays(7));
        }

        return response()->json($payload, 201);
    }

    /**
     * قراءة السلة مع قفل الصف — تتنادى جوه Transaction فقط.
     * ترتيب الأقفال هنا: Cart → items → (بعدين createOrder بيقفل Products).
     */
    protected function lockedCartItems(Request $request): array
    {
        $cart = $this->cartService->getCart($request->user());

        // قفل صف السلة: أي checkout متزامن لنفس المستخدم هيستنى هنا
        Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();

        return $cart->items()->lockForUpdate()->get()
            ->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity])
            ->values()
            ->all();
    }

    /** قراءة عادية للسلة — للـ quote بس (مفيش كتابة فمفيش سباق) */
    protected function cartItems(Request $request): array
    {
        return $this->cartService->getCart($request->user())
            ->items
            ->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity])
            ->values()
            ->all();
    }
}
