<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search'    => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $customers = User::customers()
            ->withCount('ordersAsCustomer as orders_count')
            ->withSum(['ordersAsCustomer as total_spent' => function ($q) {
                $q->whereIn('status', Order::SALE_STATUSES);
            }], 'total')
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('phone', 'like', "%{$request->search}%");
                });
            })
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate(20);

        return response()->json($customers);
    }

    public function show(User $customer)
    {
        abort_if($customer->type !== 'customer', 404);

        return response()->json([
            'customer' => $customer,
            'stats'    => [
                'orders_count' => $customer->ordersAsCustomer()->count(),
                'total_spent'  => (float) $customer->ordersAsCustomer()
                    ->whereIn('status', Order::SALE_STATUSES)->sum('total'),
            ],
            'recent_orders' => $customer->ordersAsCustomer()
                ->latest()->take(10)
                ->get(['id', 'order_number', 'status', 'total', 'created_at']),
        ]);
    }

    public function toggleStatus(User $customer)
    {
        abort_if($customer->type !== 'customer', 404);

        $customer->update(['is_active' => !$customer->is_active]);

        if (!$customer->is_active) {
            $customer->tokens()->delete(); // نقفل كل جلساته
        }

        AuditLog::record('customer.toggled', $customer, [
            'is_active' => $customer->is_active,
        ]);

        return response()->json($customer);
    }
}
