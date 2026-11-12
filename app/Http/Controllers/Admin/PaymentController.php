<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\AuditLog;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:pending,paid,failed,refunded',
            'from'   => 'nullable|date',
            'to'     => 'nullable|date|after_or_equal:from',
        ]);

        $payments = Payment::with('order:id,order_number,customer_name,total,status')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_method, fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($request->search, function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->where('transaction_id', 'like', "%{$s}%")
                      ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$s}%"));
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json($payments);
    }

    public function show(Payment $payment)
    {
        return response()->json($payment->load('order.customer:id,name,phone'));
    }

      public function updateStatus(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'status'         => 'required|in:paid,failed,refunded',
            // التأكيد اليدوي لازم يكون معاه رقم العملية كإثبات
            'transaction_id' => [
                'nullable', 'string', 'max:100',
                Rule::unique('payments', 'transaction_id')->ignore($payment->id),
                Rule::requiredIf(fn () => $request->input('status') === 'paid'),
            ],
        ]);

        // فصل الصلاحيات: تأكيد/فشل الدفع غير الاسترداد
        $needed = $data['status'] === 'refunded' ? 'payments.refund' : 'payments.confirm';

        if (!$request->user()->hasPermission($needed)) {
            return response()->json(['message' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء'], 403);
        }

        $from = $payment->status;

        try {
            $payment = $this->paymentService->updateStatus($payment, $data['status'], $data['transaction_id'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLog::record('payment.' . $data['status'], $payment, [
            'from'           => $from,
            'order_id'       => $payment->order_id,
            'amount'         => (float) $payment->amount,
            'transaction_id' => $data['transaction_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'تم تحديث حالة الدفع',
            'payment' => $payment->load('order:id,order_number,status'),
        ]);
    }
}
