<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InfluencerWallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InfluencerWalletController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        $wallets = InfluencerWallet::with('influencer:id,name,email,phone')
            ->when($request->search, function ($q, $s) {
                $q->whereHas('influencer', function ($u) use ($s) {
                    $u->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%")
                      ->orWhere('phone', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('balance')
            ->paginate(20);

        return response()->json([
            'totals' => [
                'balance'         => (float) InfluencerWallet::sum('balance'),
                'total_earned'    => (float) InfluencerWallet::sum('total_earned'),
                'total_withdrawn' => (float) InfluencerWallet::sum('total_withdrawn'),
            ],
            'wallets' => $wallets,
        ]);
    }

    public function show(Request $request, InfluencerWallet $wallet)
    {
        $request->validate(['type' => 'nullable|in:credit,debit']);

        $transactions = $wallet->transactions()
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->latest()
            ->paginate(20);

        return response()->json([
            'wallet'              => $wallet->load('influencer:id,name,email,phone'),
            'pending_withdrawals' => (float) WithdrawalRequest::where('wallet_id', $wallet->id)
                                        ->where('status', 'pending')->sum('amount'),
            'transactions'        => $transactions,
        ]);
    }

    /** كل المعاملات المالية لكل المؤثرين */
    public function transactions(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:credit,debit',
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $transactions = WalletTransaction::with('wallet.influencer:id,name')
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->influencer_id, function ($q, $id) {
                $q->whereHas('wallet', fn ($w) => $w->where('influencer_id', $id));
            })
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->latest()
            ->paginate(30);

        return response()->json($transactions);
    }
}
