<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\HomeController; // <--- تمت الإضافة
use App\Http\Controllers\Controller;
use App\Http\Resources\StoryResource;
use App\Models\AuditLog;
use App\Models\InfluencerStory;
use App\Services\InfluencerStoryService;
use Illuminate\Http\Request;

class InfluencerStoryController extends Controller
{
    public function __construct(
        protected InfluencerStoryService $stories
    ) {}

    /** GET admin/stories: القصص الحالية (بما فيها المخفية) */
    public function index(Request $request)
    {
        $request->validate([
            'influencer_id' => 'nullable|integer',
            'is_active'     => 'nullable|boolean',
        ]);

        $list = InfluencerStory::query()
            ->where('expires_at', '>', now())
            ->when($request->influencer_id, fn ($q, $id) => $q->where('influencer_id', $id))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->with(['influencer:id,name,avatar', 'storyProducts.product', 'storyProducts.promotionalLink'])
            ->withCount(['likers', 'viewers'])
            ->latest()
            ->paginate(20);

        return StoryResource::collection($list);
    }

    /** PATCH admin/stories/{story}/status */
    public function updateStatus(Request $request, InfluencerStory $story)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);

        $this->stories->setActive($story, (bool) $data['is_active']);

        AuditLog::record(
            $data['is_active'] ? 'story.shown' : 'story.hidden',
            $story,
            ['influencer_id' => $story->influencer_id]
        );

        // مسح كاش الصفحة الرئيسية لأن القصص تظهر هناك
        HomeController::flush();

        return response()->json([
            'message'   => $data['is_active'] ? 'تم إظهار القصة' : 'تم إخفاء القصة',
            'is_active' => (bool) $data['is_active'],
        ]);
    }

    /** DELETE admin/stories/{story} */
    public function destroy(InfluencerStory $story)
    {
        AuditLog::record('story.deleted', $story, ['influencer_id' => $story->influencer_id]);

        $this->stories->delete($story);

        // مسح كاش الصفحة الرئيسية لأن القصص تظهر هناك
        HomeController::flush();

        return response()->json(['message' => 'تم حذف القصة']);
    }
}