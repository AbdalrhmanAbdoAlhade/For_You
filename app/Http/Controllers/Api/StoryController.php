<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryRailResource;
use App\Http\Resources\StoryResource;
use App\Models\InfluencerStory;
use App\Models\InfluencerStoryProduct;
use App\Models\User;
use App\Services\FollowService;
use App\Services\InfluencerStoryService;
use App\Services\LikeService;
use Illuminate\Http\Request;


class StoryController extends Controller
{
    public function __construct(
        protected InfluencerStoryService $stories,
        protected FollowService $follows,
        protected LikeService $likes,
    ) {}

    /** GET stories/rail (auth): صف "Creators you follow" */
    public function rail(Request $request)
    {
        return StoryRailResource::collection(
            $this->stories->followedRail($request->user())
        );
    }

    /** GET stories/discover: مؤثرين عندهم قصص حالية (للزائر أو لمن لا يتابع أحدًا) */
    public function discover(Request $request)
    {
        return StoryRailResource::collection(
            $this->stories->discoverRail($request->user('sanctum'))
        );
    }

    /** GET influencers/{id}/stories: قصص مؤثر واحد بالترتيب (الأقدم أولًا) */
    public function creator(Request $request, int $id)
    {
        $influencer = User::influencers()->active()->findOrFail($id);
        $viewer     = $request->user('sanctum');

        $stories = $this->stories->storiesFor($influencer, $viewer);

        return StoryResource::collection($stories)->additional([
            'influencer' => [
                'id'           => $influencer->id,
                'name'         => $influencer->name,
                'avatar'       => $influencer->avatar,
                'is_following' => $viewer ? $this->follows->isFollowing($viewer, $influencer->id) : null,
            ],
        ]);
    }

    /** GET stories/featured: كارت "Creator edit" في الهوم (من آخر قصة حيّة فيها منتجات متاحة) */
  
/** GET stories/featured: أفضل مؤثر حسب الطلبات المسلّمة آخر 7 أيام */
public function featured()
{
    $winner = $this->stories->discoverRail(null, 1)->first();

    if (!$winner) {
        return response()->json(['data' => null]);
    }

    $winner->loadMissing('influencerProfile');

    return response()->json([
        'data' => [
            'id'                => (int) $winner->id,
            'name'              => (string) $winner->name,
            'avatar'            => $winner->avatar,
            'cover_image'       => $winner->influencerProfile?->cover_image,
            'instagram_url'     => $winner->influencerProfile?->instagram_url,
            'conversions_count' => (int) ($winner->weekly_delivered_orders_count ?? 0),
        ],
    ]);
}


    /** POST stories/{id}/view (auth): تسجيل مشاهدة (مرة واحدة لكل مستخدم) */
    public function view(Request $request, int $id)
    {
        $story = InfluencerStory::visible()->findOrFail($id);

        $this->stories->markViewed($story, $request->user());

        return response()->noContent();
    }

    /** POST stories/{id}/like (auth) */
    public function like(Request $request, int $id)
    {
        $story = InfluencerStory::visible()->findOrFail($id);

        return response()->json($this->likes->likeStory($request->user(), $story));
    }

    /** DELETE stories/{id}/like (auth) */
    public function unlike(Request $request, int $id)
    {
        $story = InfluencerStory::findOrFail($id);

        return response()->json($this->likes->unlikeStory($request->user(), $story));
    }
}