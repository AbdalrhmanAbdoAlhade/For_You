<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $coupons = Coupon::when($request->search, fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate(20);

        return response()->json($coupons);
    }

    public function show(Coupon $coupon)
    {
        return response()->json($coupon);
    }

    public function store(Request $request)
    {
        $this->normalizeCode($request);

        $data = $request->validate($this->rules());
        $this->assertPercentLimit($data['type'], (float) $data['value']);

        return response()->json(Coupon::create($data), 201);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->normalizeCode($request);

        $rules = collect($this->rules($coupon))
            ->map(fn ($r) => is_array($r) ? array_merge(['sometimes'], $r) : 'sometimes|' . $r)
            ->all();

        $data = $request->validate($rules);

        $this->assertPercentLimit(
            $data['type'] ?? $coupon->type,
            (float) ($data['value'] ?? $coupon->value)
        );

        $coupon->update($data);

        return response()->json($coupon);
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->used_count > 0) {
            return response()->json(
                ['message' => 'الكوبون مستخدم بالفعل، عطّله بدل الحذف'],
                422
            );
        }

        $coupon->delete();

        return response()->json(['message' => 'تم حذف الكوبون']);
    }

    protected function rules(?Coupon $coupon = null): array
    {
        return [
            'code'             => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type'             => 'required|in:percent,fixed',
            'value'            => 'required|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount'     => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'per_user_limit'   => 'nullable|integer|min:1',
            'is_active'        => 'boolean',
            'starts_at'        => 'nullable|date',
            'expires_at'       => 'nullable|date|after:starts_at',
        ];
    }

    protected function normalizeCode(Request $request): void
    {
        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim($request->code))]);
        }
    }

    protected function assertPercentLimit(string $type, float $value): void
    {
        if ($type === 'percent' && $value > 100) {
            throw ValidationException::withMessages(['value' => __('النسبة لا يمكن أن تتجاوز 100')]);
        }
    }
}
