<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

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

    /**
     * تعديل بيانات عميل حسب الـ id (أدمن أو موظف بصلاحية customers.manage).
     * يدعم POST multipart لرفع صورة avatar.
     */
    public function update(Request $request, User $customer)
    {
        abort_if($customer->type !== 'customer', 404);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone'     => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($customer->id)],
            'password'  => 'nullable|string|min:8',
            'is_active' => 'sometimes|boolean',
            'avatar'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=2000,max_height=2000',
        ]);

        $passwordChanged = !empty($data['password']);
        $emailChanged    = isset($data['email']) && $data['email'] !== $customer->email;

        if ($passwordChanged) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($customer->avatar && !str_starts_with($customer->avatar, 'http')) {
                $this->fileUploadService->delete($customer->avatar);
            }
            $data['avatar'] = $this->fileUploadService->upload($request->file('avatar'), 'avatars');
        } else {
            unset($data['avatar']);
        }

        $customer->fill($data);

        if ($emailChanged) {
            $customer->email_verified_at = null;
        }

        $customer->save();

        $deactivated = array_key_exists('is_active', $data) && !$customer->is_active;

        // تغيير الباسورد أو تعطيل الحساب = قفل كل الجلسات
        if ($passwordChanged || $deactivated) {
            $customer->tokens()->delete();
        }

        AuditLog::record('customer.updated', $customer, [
            'fields'           => array_keys($data),
            'password_changed' => $passwordChanged,
            'email_changed'    => $emailChanged,
        ]);

        return response()->json($customer->fresh());
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