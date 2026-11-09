<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipping;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ShippingController extends Controller
{
    protected const STATUSES = 'pending,picked_up,in_transit,out_for_delivery,delivered,failed,returned';

    public function __construct(
        protected ShippingService $shippingService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:' . self::STATUSES,
            'from'   => 'nullable|date',
            'to'     => 'nullable|date|after_or_equal:from',
        ]);

        $shipments = Shipping::with('order:id,order_number,customer_name,customer_phone,city,status')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->shipping_company, fn ($q, $c) => $q->where('shipping_company', $c))
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($request->search, function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->where('tracking_number', 'like', "%{$s}%")
                      ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$s}%"));
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json($shipments);
    }

    public function show(Shipping $shipping)
    {
        return response()->json($shipping->load('order.items', 'order.payment'));
    }

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'shipping_company' => 'required|string|max:100',
            'tracking_number'  => 'nullable|string|max:100|unique:shippings,tracking_number',
            'shipping_address' => 'nullable|string',
        ]);

        try {
            $shipping = $this->shippingService->createForOrder($order, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($shipping, 201);
    }

    public function update(Request $request, Shipping $shipping)
    {
        $data = $request->validate([
            'shipping_company' => 'sometimes|string|max:100',
            'tracking_number'  => 'nullable|string|max:100|unique:shippings,tracking_number,' . $shipping->id,
            'shipping_address' => 'sometimes|string',
        ]);

        $shipping->update($data);

        return response()->json($shipping);
    }

    public function updateStatus(Request $request, Shipping $shipping)
    {
        $data = $request->validate([
            'status' => 'required|in:' . self::STATUSES,
        ]);

        try {
            $shipping = $this->shippingService->updateStatus($shipping, $data['status']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'  => 'تم تحديث حالة الشحنة',
            'shipping' => $shipping->load('order:id,order_number,status'),
        ]);
    }
}
