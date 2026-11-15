<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|in:pending,approved,rejected,paid']);

        $withdrawals = WithdrawalRequest::with([
                'influencer:id,name,phone',
                'wallet:id,influencer_id,balance',
                'approver:id,name',
                'payer:id,name',
            ])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($withdrawals);
    }

    public function process(Request $request, WithdrawalRequest $withdrawal)
    {
        $data = $request->validate([
            'status'             => 'required|in:approved,rejected,paid',
            'admin_note'         => 'nullable|string|max:1000',
            'transfer_reference' => 'required_if:status,paid|nullable|string|max:100',
        ]);

        // فصل الصلاحيات: الموافقة/الرفض غير التنفيذ (الدفع)
        $needed = $data['status'] === 'paid' ? 'withdrawals.pay' : 'withdrawals.approve';

        if (!$request->user()->hasPermission($needed)) {
            return response()->json(['message' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء'], 403);
        }

        $from = $withdrawal->status;

        try {
            $withdrawal = $this->walletService->processWithdrawal(
                $withdrawal,
                $data['status'],
                $request->user(),
                $data['admin_note'] ?? null,
                $data['transfer_reference'] ?? null
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLog::record('withdrawal.' . $data['status'], $withdrawal, [
            'from'               => $from,
            'amount'             => (float) $withdrawal->amount,
            'transfer_reference' => $data['transfer_reference'] ?? null,
        ]);

        return response()->json([
            'message'    => 'تم معالجة طلب السحب بنجاح',
            'withdrawal' => $withdrawal,
        ]);
    }
}
