<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\Request;

class StoreInfluencerController extends Controller
{
    public function __construct(
        protected FollowService $follows
    ) {}

    public function index(Request $request)
    {
        $activePivot = function ($q) {
            $q->where('products.is_active', true)->where('influencer_product.is_active', true);
        };

        $influencers = User::influencers()->active()
            ->whereHas('influencerProducts', $activePivot)
            ->withCount(['influencerProducts as products_count' => $activePivot])
            ->withCount(['stories as live_stories_count' => fn ($q) => $q->live()])
            ->orderBy('name')
            ->paginate(12);

        $ids      = $influencers->getCollection()->pluck('id')->all();
        $viewer   = $request->user('sanctum');
        $counts   = $this->follows->followersCounts($ids);
        $followed = $viewer ? $this->follows->followedAmong($viewer, $ids) : [];

        // بيانات عامة بس (من غير إيميل أو تليفون)
        $influencers->through(fn ($u) => [
            'id'              => $u->id,
            'name'            => $u->name,
            'avatar'          => $u->avatar,
            'products_count'  => $u->products_count,
            'followers_count' => $counts[$u->id] ?? 0,
            'is_following'    => $viewer ? in_array($u->id, $followed) : null,
            'has_stories'     => $u->live_stories_count > 0,
        ]);

        return response()->json($influencers);
    }

    public function products(Request $request, int $id)
    {
        $influencer = User::influencers()->active()->findOrFail($id);
        $viewer     = $request->user('sanctum');

        $products = $influencer->influencerProducts()
            ->where('products.is_active', true)
            ->wherePivot('is_active', true)
            ->with('category')
            ->latest('products.created_at')
            ->paginate(15);

        return ProductResource::collection($products)->additional([
            'influencer' => [
                'id'              => $influencer->id,
                'name'            => $influencer->name,
                'avatar'          => $influencer->avatar,
                'followers_count' => $this->follows->followersCounts([$influencer->id])[$influencer->id] ?? 0,
                'is_following'    => $viewer ? $this->follows->isFollowing($viewer, $influencer->id) : null,
            ],
        ]);
    }
}