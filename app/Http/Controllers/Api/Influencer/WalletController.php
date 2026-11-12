<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function show(Request $request)
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user()->id);

        $pending = $this->walletService->pendingWithdrawals($wallet);

        // عمولات لسه في فترة الانتظار (مش داخلة في الرصيد ولا قابلة للسحب)
        $held = $request->user()->commissions()->where('status', 'pending');

        return response()->json([
            'wallet' => [
                'held_commissions'    => round((float) (clone $held)->sum('commission_amount'), 2),
                'next_release_at'     => (clone $held)->min('available_at'),
                'balance'             => (float) $wallet->balance,
                'pending_withdrawals' => $pending,
                'available_balance'   => (float) $wallet->balance - $pending,
                'total_earned'        => (float) $wallet->total_earned,
                'total_withdrawn'     => (float) $wallet->total_withdrawn,
                'currency'            => 'EGP',
                'min_withdrawal'      => WalletService::MIN_WITHDRAWAL,
            ],
            'transactions' => $wallet->transactions()->latest()->paginate(20),
        ]);
    }

    public function requestWithdrawal(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:' . WalletService::MIN_WITHDRAWAL,
            'payment_method' => [
                'required',
                'string',
                Rule::in(['vodafone_cash', 'instapay', 'bank_transfer', 'orange_cash', 'etisalat_cash']),
            ],
            'account_details' => 'required|string|max:255',
        ]);

        try {
            $withdrawal = $this->walletService->requestWithdrawal(
                $request->user(),
                (float) $data['amount'],
                $data['payment_method'],
                $data['account_details']
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($withdrawal, 201);
    }

    public function myWithdrawals(Request $request)
    {
        $withdrawals = WithdrawalRequest::where('influencer_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($withdrawals);
    }
}
