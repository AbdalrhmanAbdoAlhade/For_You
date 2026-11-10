<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        $withdrawals = WithdrawalRequest::with(['influencer:id,name,phone', 'wallet:id,influencer_id,balance'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($withdrawals);
    }

    public function process(Request $request, WithdrawalRequest $withdrawal)
    {
        $data = $request->validate([
            'status'     => 'required|in:approved,rejected,paid',
            'admin_note' => 'nullable|string',
        ]);

        try {
            $withdrawal = $this->walletService->processWithdrawal(
                $withdrawal,
                $data['status'],
                $data['admin_note'] ?? null
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'    => 'تم معالجة طلب السحب بنجاح',
            'withdrawal' => $withdrawal,
        ]);
    }
}