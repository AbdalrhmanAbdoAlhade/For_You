<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}
// داخل Api/Influencer/WalletController

public function requestWithdrawal(Request $request)
{
    $data = $request->validate([
        'amount'          => 'required|numeric|min:50',
        'payment_method'  => 'required|string',
        'account_details' => 'required|string',
    ]);

    try {
        $withdrawal = $this->walletService->requestWithdrawal(
            $request->user(),
            $data['amount'],
            $data['payment_method'],
            $data['account_details']
        );

        return response()->json($withdrawal, 201);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}

public function myWithdrawals(Request $request)
{
    $withdrawals = WithdrawalRequest::where('influencer_id', $request->user()->id)
        ->latest()
        ->paginate(15);

    return response()->json($withdrawals);
}
    public function show(Request $request)
    {
        $influencer = $request->user();
        abort_if($influencer->type !== 'influencer', 403);

        $wallet = $this->walletService->getOrCreateWallet($influencer->id);

        return response()->json([
            'wallet'       => $wallet,
            'transactions' => $wallet->transactions()->latest()->paginate(20),
        ]);
    }
}
