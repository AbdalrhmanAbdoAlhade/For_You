<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Services\PromotionalLinkService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InfluencerController extends Controller
{
    public function __construct(
        protected PromotionalLinkService $linkService,
        protected WalletService $walletService
    ) {}


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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|unique:users,phone',
            'password' => 'required|string|min:8',
            'is_active'=> 'boolean',
        ]);

        $influencer = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'type'     => 'influencer',
        ]);

        // إنشاء محفظة تلقائيًا
        $this->walletService->getOrCreateWallet($influencer->id);

        return response()->json($influencer, 201);
    }

    public function show(User $influencer)
    {
        abort_if($influencer->type !== 'influencer', 404);

        return response()->json(
            $influencer->load(['wallet', 'influencerProducts', 'promotionalLinks', 'commissions'])
        );
    }

    public function update(Request $request, User $influencer)
    {
        abort_if($influencer->type !== 'influencer', 404);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|unique:users,email,' . $influencer->id,
            'phone'     => 'nullable|string|unique:users,phone,' . $influencer->id,
            'password'  => 'nullable|string|min:8',
            'is_active' => 'boolean',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $influencer->update($data);

        return response()->json($influencer);
    }

    public function attachProducts(Request $request, User $influencer)
    {
        abort_if($influencer->type !== 'influencer', 404);

        $data = $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.commission_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $syncData = [];
        foreach ($data['products'] as $item) {
            $syncData[$item['product_id']] = [
                'commission_percentage' => $item['commission_percentage'] ?? 10,
                'is_active' => true,
            ];
        }

        $influencer->influencerProducts()->sync($syncData);

        return response()->json([
            'message' => 'تم ربط المنتجات بالمؤثر بنجاح',
            'products' => $influencer->influencerProducts,
        ]);
    }

    public function generateLink(Request $request, User $influencer)
    {
        abort_if($influencer->type !== 'influencer', 404);

        $request->validate([
            'product_id' => 'nullable|exists:products,id',
        ]);

        $link = $this->linkService->generate($influencer, $request->product_id);

        return response()->json($link, 201);
    }
}
