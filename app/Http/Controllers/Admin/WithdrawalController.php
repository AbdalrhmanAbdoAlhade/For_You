<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;


class WithdrawalController extends Controller
{
    // Admin/WithdrawalController.php

public function index(Request $request)
{
    $withdrawals = WithdrawalRequest::with(['influencer', 'wallet'])
        ->when($request->status, fn($q) => $q->where('status', $request->status))
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

    $this->walletService->processWithdrawal(
        $withdrawal,
        $data['status'],
        $data['admin_note'] ?? null
    );

    return response()->json(['message' => 'تم معالجة طلب السحب بنجاح']);
}
}
