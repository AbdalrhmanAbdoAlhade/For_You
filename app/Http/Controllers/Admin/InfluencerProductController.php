<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PromotionalLink;
use App\Models\User;
use App\Services\InfluencerCommissionService;
use Illuminate\Http\Request;

class InfluencerProductController extends Controller
{
    /** المؤثرين المرتبطين بمنتج */
    public function forProduct(Product $product)
    {
        $influencers = $product->influencers()->get()->map(fn ($u) => [
            'influencer_id'         => $u->id,
            'name'                  => $u->name,
            'account_active'        => $u->is_active,
            'commission_percentage' => InfluencerCommissionService::resolvePercentage($u->pivot->commission_percentage),
            'is_active'             => (bool) $u->pivot->is_active,
        ]);

        return response()->json($influencers);
    }

    /** POST influencers/{influencer}/products/{product} */
    public function attach(Request $request, User $influencer, Product $product)
    {
        abort_if($influencer->type !== 'influencer', 404);

        $data = $request->validate($this->rules());

        return $this->link($influencer, $product, $data);
    }

    /** POST products/{product}/influencers  (influencer_id في الـ body) */
    public function attachFromProduct(Request $request, Product $product)
    {
        $data = $request->validate(['influencer_id' => 'required|integer'] + $this->rules());

        $influencer = User::influencers()->findOrFail($data['influencer_id']);

        return $this->link($influencer, $product, $data);
    }

    /** PATCH influencers/{influencer}/products/{product} */
    public function update(Request $request, User $influencer, Product $product)
    {
        abort_if($influencer->type !== 'influencer', 404);

        $data = $request->validate([
            'commission_percentage' => 'sometimes|numeric|min:0|max:100',
            'is_active'             => 'sometimes|boolean',
        ]);

        if (empty($data)) {
            return response()->json(['message' => 'ابعت النسبة أو الحالة المراد تعديلها'], 422);
        }

        if (!$this->isLinked($influencer, $product)) {
            return response()->json(['message' => 'المنتج غير مرتبط بهذا المؤثر'], 404);
        }

        $influencer->influencerProducts()->updateExistingPivot($product->id, $data);

        return response()->json(['message' => 'تم تعديل الربط', 'link' => $this->show($influencer, $product)]);
    }

    /** DELETE influencers/{influencer}/products/{product} */
    public function detach(User $influencer, Product $product)
    {
        abort_if($influencer->type !== 'influencer', 404);

        if (!$this->isLinked($influencer, $product)) {
            return response()->json(['message' => 'المنتج غير مرتبط بهذا المؤثر'], 404);
        }

        $influencer->influencerProducts()->detach($product->id);

        // روابط المؤثر الخاصة بالمنتج ده بتتوقف، لأنه مبقاش من منتجاته
        PromotionalLink::where('influencer_id', $influencer->id)
            ->where('product_id', $product->id)
            ->update(['is_active' => false]);

        return response()->json(['message' => 'تم فصل المنتج عن المؤثر']);
    }

    // ---------------------------------------------------------------

    protected function link(User $influencer, Product $product, array $data)
    {
        if ($this->isLinked($influencer, $product)) {
            return response()->json(
                ['message' => 'المنتج مرتبط بالفعل بهذا المؤثر، استخدم التعديل'],
                409
            );
        }

        // null = النسبة الافتراضية (بتتحسب وقت العرض والتسوية، فتغيير الافتراضي بيسري على الكل)
        $influencer->influencerProducts()->attach($product->id, [
            'commission_percentage' => $data['commission_percentage'] ?? null,
            'is_active'             => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'تم ربط المنتج بالمؤثر',
            'link'    => $this->show($influencer, $product),
        ], 201);
    }

    protected function isLinked(User $influencer, Product $product): bool
    {
        return $influencer->influencerProducts()->where('products.id', $product->id)->exists();
    }

    protected function show(User $influencer, Product $product): array
    {
        $p = $influencer->influencerProducts()->where('products.id', $product->id)->first();

        return [
            'influencer_id'         => $influencer->id,
            'product_id'            => $product->id,
            'commission_percentage' => InfluencerCommissionService::resolvePercentage($p->pivot->commission_percentage),
            'is_active'             => (bool) $p->pivot->is_active,
        ];
    }

    protected function rules(): array
    {
        return [
            'commission_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active'             => 'boolean',
        ];
    }
}
