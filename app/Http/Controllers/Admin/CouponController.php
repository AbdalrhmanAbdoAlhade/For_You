<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search'    => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

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
        $this->assertDateRange($data['starts_at'] ?? null, $data['expires_at'] ?? null);

        $coupon = Coupon::create($data);

        AuditLog::record('coupon.created', $coupon, ['code' => $coupon->code]);

        return response()->json($coupon, 201);
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

        /*
         * مقارنة صريحة بالقيم المحفوظة: after:starts_at بيفشل لو starts_at
         * مش مبعوت في الطلب (تحديث جزئي لتاريخ الانتهاء فقط).
         */
        $this->assertDateRange(
            $data['starts_at'] ?? $coupon->starts_at,
            $data['expires_at'] ?? $coupon->expires_at
        );

        $coupon->update($data);

        AuditLog::record('coupon.updated', $coupon, ['code' => $coupon->code]);

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

        AuditLog::record('coupon.deleted', $coupon, ['code' => $coupon->code]);

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
            'expires_at'       => 'nullable|date',
        ];
    }

    /**
     * نهاية الكوبون لازم تكون بعد بدايته (مقارنة صريحة تدعم التحديث الجزئي).
     */
    protected function assertDateRange(mixed $startsAt, mixed $expiresAt): void
    {
        if (empty($startsAt) || empty($expiresAt)) {
            return;
        }

        if (Carbon::parse($expiresAt)->lessThanOrEqualTo(Carbon::parse($startsAt))) {
            throw ValidationException::withMessages([
                'expires_at' => __('تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية'),
            ]);
        }
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
