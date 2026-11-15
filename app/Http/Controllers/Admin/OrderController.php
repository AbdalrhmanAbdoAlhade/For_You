<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:new,confirmed,processing,ready_for_shipping,shipped,delivered,cancelled,returned',
            'search' => 'nullable|string|max:100',
        ]);

        $orders = Order::with(['customer', 'influencer', 'payment', 'shipping'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('order_number', 'like', "%{$request->search}%")
                      ->orWhere('customer_name', 'like', "%{$request->search}%")
                      ->orWhere('customer_phone', 'like', "%{$request->search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function show(Order $order)
    {
        return response()->json(
            $order->load(['customer', 'influencer', 'items.product', 'payment', 'shipping', 'commissions'])
        );
    }

           public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => 'required|in:new,confirmed,processing,ready_for_shipping,shipped,delivered,cancelled,returned',
        ]);

        $user = $request->user();

        // الإلغاء والإرجاع محتاجين orders.cancel أو orders.manage، والباقي orders.update أو orders.manage
        $needed = in_array($data['status'], ['cancelled', 'returned'], true)
            ? ['orders.cancel', 'orders.manage']
            : ['orders.update', 'orders.manage'];

        if (!collect($needed)->contains(fn ($p) => $user->hasPermission($p))) {
            return response()->json(['message' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء'], 403);
        }

        $from = $order->status;

        try {
            $order = $this->orderService->updateStatus($order, $data['status']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLog::record('order.status', $order, ['from' => $from, 'to' => $data['status']]);

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'order'   => $order->load('payment', 'shipping'),
        ]);
    }
}
