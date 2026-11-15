<?php

namespace App\Http\Controllers\Api\Influencer;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryResource;
use App\Services\InfluencerStoryService;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function __construct(
        protected InfluencerStoryService $stories
    ) {}

    /** GET influencer/stories: قصصي الحالية + الحصة المتبقية */
    public function index(Request $request)
    {
        $user = $request->user();

        return StoryResource::collection($this->stories->listForInfluencer($user))
            ->additional(['quota' => $this->stories->quota($user)]);
    }

    /**
     * POST influencer/stories (multipart/form-data)
     * image + product_ids[] (+ caption, label)
     */
    public function store(Request $request)
    {
        $max = max(1, (int) config('store.story_max_products', 10));

        $data = $request->validate([
            'image'         => 'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=6000,max_height=6000',
            'caption'       => 'nullable|string|max:300',
            'label'         => 'nullable|string|max:60',
            'product_ids'   => "required|array|min:1|max:{$max}",
            'product_ids.*' => 'integer|distinct',
        ]);

        $user  = $request->user();
        $story = $this->stories->create($user, $request->file('image'), $data);
        $story->loadCount(['likers', 'viewers']);

        return (new StoryResource($story))
            ->additional(['quota' => $this->stories->quota($user)])
            ->response()
            ->setStatusCode(201);
    }

    /** PATCH influencer/stories/{id}: إخفاء/إظهار قبل الانتهاء */
    public function update(Request $request, int $id)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);

        $story = $request->user()->stories()
            ->where('expires_at', '>', now())
            ->findOrFail($id);

        $story = $this->stories->setActive($story, (bool) $data['is_active']);
        $story->loadCount(['likers', 'viewers']);

        return new StoryResource($story);
    }

    /** DELETE influencer/stories/{id} */
    public function destroy(Request $request, int $id)
    {
        $story = $request->user()->stories()->findOrFail($id);

        $this->stories->delete($story);

        return response()->json([
            'message' => 'تم حذف القصة',
            'quota'   => $this->stories->quota($request->user()),
        ]);
    }
}