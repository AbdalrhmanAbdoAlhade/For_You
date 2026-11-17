<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatorProfileResource;
use App\Http\Resources\ProductResource;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatorProfileController extends Controller
{
    public function __construct(
        protected FollowService $follows,
    ) {}

    /** GET /api/influencers/{id} */
    public function show(Request $request, int $id)
    {
        $viewer = $request->user('sanctum');

        // Eager: influencerProfile + brands (active) + counts
        $influencer = User::influencers()
            ->active()
            ->with([
                'influencerProfile',
                'brands' => fn ($q) => $q->where('brands.is_active', true)
                    ->wherePivot('is_active', true),
            ])
            ->withCount([
                // عدد المنتجات النشطة المرتبطة
                'influencerProducts as picks_count' => fn ($q) => $q
                    ->where('products.is_active', true)
                    ->where('influencer_product.is_active', true),
            ])
            ->findOrFail($id);

        // followers_count/following_count من influencer_follows (مفيش تخزين مكرر)
        $followCounts = $this->follows->followersCounts([$influencer->id]);

        // reviews المنتجات المرتبطة
        $relatedProductIds = $influencer->influencerProducts()
            ->wherePivot('is_active', true)
            ->pluck('products.id');

        $reviewsAgg = ProductReview::query()
            ->active()
            ->whereIn('product_id', $relatedProductIds)
            ->selectRaw('COUNT(*) as cnt, COALESCE(AVG(rating), 0) as avg')
            ->first();

        $influencer->stats = [
            'followers_count' => (int) ($followCounts[$influencer->id] ?? 0),
            'following_count' => (int) $influencer->following()->count(),
            'picks_count'     => (int) $influencer->picks_count,
            'products_count'  => (int) $influencer->picks_count,
            'reviews_count'   => (int) ($reviewsAgg->cnt ?? 0),
            'reviews_avg'     => round((float) ($reviewsAgg->avg ?? 0), 2),
        ];

        $influencer->is_following = $viewer
            ? $this->follows->isFollowing($viewer, $influencer->id)
            : null;

        return (new CreatorProfileResource($influencer))->response();
    }

    /** GET /api/influencers/{id}/picks */
    public function picks(Request $request, int $id)
    {
        // نفس مصدر StoreInfluencerController::products() — توحيد منطق
        $influencer = User::influencers()->active()->findOrFail($id);

        $data = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $perPage = (int) ($data['per_page'] ?? 15);

        $products = $influencer->influencerProducts()
            ->where('products.is_active', true)
            ->wherePivot('is_active', true)
            ->with('category')
            ->withCount('variants')
            ->latest('products.created_at')
            ->orderByDesc('products.id')
            ->paginate($perPage);

        return ProductResource::collection($products);
    }
}
