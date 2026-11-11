<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $influencers = User::influencers()
            ->with('wallet')
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('phone', 'like', "%{$request->search}%");
                });
            })
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate(20);

        return response()->json($influencers);
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

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $influencer->update($data);

        return response()->json($influencer);
    }

    public function destroy(User $influencer)
    {
        abort_if($influencer->type !== 'influencer', 404);

        $influencer->tokens()->delete();
        $influencer->delete();

        return response()->json(['message' => 'تم حذف المؤثر بنجاح']);
    }

   public function attachProducts(Request $request, User $influencer)
{
    abort_if($influencer->type !== 'influencer', 404);

    $data = $request->validate([
        'products' => 'required|array|min:1',
        'products.*.product_id' => 'required|integer|distinct|exists:products,id',
        'products.*.commission_percentage' => 'nullable|numeric|min:0|max:100',
    ]);

    $attach = [];
    foreach ($data['products'] as $item) {
        $pivot = ['is_active' => true];

        // النسبة بتتكتب بس لو اتبعتت (null صريحة = استخدم الافتراضي).
        // لو مش مبعوتة: المنتج الجديد بياخد null، والمربوط قبل كده بتفضل نسبته زي ما هي.
        if (array_key_exists('commission_percentage', $item)) {
            $pivot['commission_percentage'] = $item['commission_percentage'];
        }

        $attach[$item['product_id']] = $pivot;
    }

    // إضافة/تعديل من غير ما نفصل باقي المنتجات
    $influencer->influencerProducts()->syncWithoutDetaching($attach);

    return response()->json([
        'message'  => 'تم ربط المنتجات بالمؤثر بنجاح',
        'products' => $influencer->influencerProducts()->get(),
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
