<?php

namespace App\Services;

use App\Models\InfluencerStory;
use App\Models\PromotionalLink;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InfluencerStoryService
{
    protected const EAGER = [
        'storyProducts.product.brand',
        'storyProducts.product.category',
        'storyProducts.promotionalLink',
    ];

    public function __construct(
        protected FileUploadService $uploads,
        protected PromotionalLinkService $links,
    ) {}

    // ======================================================================
    // نشر / تعديل / حذف (المؤثر)
    // ======================================================================

    /**
     * الحصة المتبقية للمؤثر (بتتحسب على آخر story_ttl_hours ساعة).
     *
     * @return array{limit:int, used:int, remaining:int, window_hours:int}
     */
    public function quota(User $influencer): array
    {
        $limit = max(1, (int) config('store.story_daily_limit', 10));
        $ttl   = max(1, (int) config('store.story_ttl_hours', 24));

        $used = InfluencerStory::query()
            ->where('influencer_id', $influencer->id)
            ->where('created_at', '>', now()->subHours($ttl))
            ->count();

        return [
            'limit'        => $limit,
            'used'         => $used,
            'remaining'    => max(0, $limit - $used),
            'window_hours' => $ttl,
        ];
    }

    /**
     * ينشر قصة: صورة واحدة + منتجات من منتجات المؤثر، ولكل منتج رابط الترويج بتاعه.
     *
     * @param array{product_ids:array<int>, caption?:?string, label?:?string} $data
     */
    public function create(User $influencer, UploadedFile $image, array $data): InfluencerStory
    {
        $productIds = $this->normalizeProductIds($data['product_ids'] ?? []);

        // فحص مبدئي قبل الرفع، عشان منعالجش صورة لطلب هيترفض
        $this->assertQuotaAvailable($influencer);
        $this->assertProductsAllowed($influencer, $productIds);

        $imageUrl = $this->uploads->upload($image, 'stories');

      
try {
    $story = DB::transaction(function () use ($influencer, $imageUrl, $data, $productIds) {
        // قفل صف المؤثر: رفعتين في نفس اللحظة ميعدّوش الحد
        User::query()->whereKey($influencer->id)->lockForUpdate()->first();

        $this->assertQuotaAvailable($influencer);
        $this->assertProductsAllowed($influencer, $productIds);

        $story = InfluencerStory::create([
            'influencer_id'    => $influencer->id,
            'image'            => $imageUrl,
            'caption'          => $data['caption'] ?? null,
            'label'            => $data['label'] ?? null,
            'duration_seconds' => max(1, (int) config('store.story_duration_seconds', 5)),
            'is_active'        => true,
        ]);

        foreach ($productIds as $index => $productId) {
            $story->storyProducts()->create([
                'product_id'          => $productId,
                'promotional_link_id' => $this->linkFor($influencer, $productId)->id,
                'sort_order'          => $index,
            ]);
        }

        return $story->load(self::EAGER);
    });

    // إرسال إشعار للمتابعين بعد نجاح الـ Transaction
    \App\Jobs\NotifyFollowersOfNewStory::dispatch($story->id)
        ->onQueue('notifications');

    return $story;

} catch (\Throwable $e) {
    $this->uploads->delete($imageUrl);

    throw $e;
}


    }

    /** إخفاء/إظهار قصة قبل انتهائها */
    public function setActive(InfluencerStory $story, bool $active): InfluencerStory
    {
        $story->update(['is_active' => $active]);

        return $story->load(self::EAGER);
    }

    /** حذف قصة (نهائي) مع صورتها. الحصة بتتحرر. */
    public function delete(InfluencerStory $story): void
    {
        $image = $story->image;

        $story->delete(); // الجداول التابعة (منتجات/مشاهدات/لايكات) بتتمسح بالـ cascade

        $this->uploads->delete($image);
    }

    /** قصص المؤثر الحالية (للوحته): بما فيها المخفية، من غير المنتهية */
    public function listForInfluencer(User $influencer): Collection
    {
        return InfluencerStory::query()
            ->where('influencer_id', $influencer->id)
            ->where('expires_at', '>', now())
            ->with(self::EAGER)
            ->withCount(['likers', 'viewers'])
            ->latest()
            ->get();
    }

    // ======================================================================
    // العرض للجمهور
    // ======================================================================

    /**
     * قصص مؤثر واحد (بالترتيب من الأقدم للأحدث زي الـ stories).
     * المنتجات اللي ماعادتش تتباع (اتفصلت/اتعطلت/الرابط انتهى) مش بتظهر.
     */
    public function storiesFor(User $influencer, ?User $viewer = null): Collection
    {
        $stories = InfluencerStory::query()
            ->visible()
            ->where('influencer_id', $influencer->id)
            ->with([
                'storyProducts' => fn ($q) => $q->shoppable()->with([
                    'product.brand',
                    'product.category',
                    'promotionalLink',
                ]),
            ])
            ->withCount(['likers', 'viewers'])
            ->orderBy('created_at')
            ->get();

        $ids   = $stories->pluck('id')->all();
        $seen  = $viewer ? $this->idsFor('influencer_story_views', $viewer->id, $ids) : [];
        $liked = $viewer ? $this->idsFor('influencer_story_likes', $viewer->id, $ids) : [];

        return $stories->each(function (InfluencerStory $story) use ($seen, $liked) {
            $story->setAttribute('is_seen', in_array($story->id, $seen));
            $story->setAttribute('is_liked', in_array($story->id, $liked));
        });
    }

    /**
     * صف "Creators you follow": كل المؤثرين اللي بيتابعهم المستخدم.
     * has_unseen = حلقة ذهبية، has_stories من غير unseen = حلقة عادية، من غير قصص = رمادي.
     */
    public function followedRail(User $viewer, int $limit = 50): Collection
    {
        $creators = $viewer->following()
            ->where('users.is_active', true)
            ->where('users.type', 'influencer')
            ->orderBy('users.name')
            ->limit($limit)
            ->get();

        return $this->buildRail($creators, $viewer);
    }

    /** مؤثرين عندهم قصص حالية (للزائر أو لمن لا يتابع أحدًا) */
    public function discoverRail(?User $viewer = null, int $limit = 20): Collection
    {
        $creators = User::query()
            ->influencers()
            ->active()
            ->whereHas('stories', fn ($q) => $q->live())
            ->withMax(['stories as latest_story_at' => fn ($q) => $q->live()], 'created_at')
            ->orderByDesc('latest_story_at')
            ->limit($limit)
            ->get();

        return $this->buildRail($creators, $viewer);
    }

    /** تسجيل مشاهدة (مرة واحدة لكل مستخدم). مشاهدة المؤثر لقصته مابتتحسبش. */
    public function markViewed(InfluencerStory $story, User $viewer): void
    {
        if ($viewer->id === $story->influencer_id || !$story->isLive()) {
            return;
        }

        DB::table('influencer_story_views')->insertOrIgnore([
            'story_id'  => $story->id,
            'user_id'   => $viewer->id,
            'viewed_at' => now(),
        ]);
    }

    // ======================================================================
    // المسح التلقائي
    // ======================================================================

    /** يمسح القصص المنتهية وصورها. بيرجّع عدد القصص اللي اتمسحت. */
    public function purgeExpired(int $chunk = 200): int
    {
        $deleted = 0;

        InfluencerStory::query()->expired()->chunkById($chunk, function ($stories) use (&$deleted) {
            InfluencerStory::whereIn('id', $stories->pluck('id')->all())->delete();

            foreach ($stories as $story) {
                $this->uploads->delete($story->image);
            }

            $deleted += $stories->count();
        });

        return $deleted;
    }

    // ======================================================================
    // Internals
    // ======================================================================

    protected function buildRail(Collection $creators, ?User $viewer): Collection
    {
        if ($creators->isEmpty()) {
            return collect();
        }

        $stories = InfluencerStory::query()
            ->live()
            ->whereIn('influencer_id', $creators->pluck('id')->all())
            ->get(['id', 'influencer_id', 'created_at']);

        $seen      = $viewer ? $this->idsFor('influencer_story_views', $viewer->id, $stories->pluck('id')->all()) : [];
        $byCreator = $stories->groupBy('influencer_id');

        return $creators
            ->map(function (User $creator) use ($byCreator, $seen) {
                $mine = $byCreator->get($creator->id, collect());

                $creator->setAttribute('stories_count', $mine->count());
                $creator->setAttribute('has_stories', $mine->isNotEmpty());
                $creator->setAttribute('has_unseen', $mine->contains(fn ($s) => !in_array($s->id, $seen)));
                $creator->setAttribute('latest_story_at', $mine->isNotEmpty() ? $mine->max('created_at') : null);

                return $creator;
            })
            ->sortBy(fn (User $c) => [
                $c->has_unseen ? 0 : ($c->has_stories ? 1 : 2),
                -($c->latest_story_at ? $c->latest_story_at->timestamp : 0),
            ])
            ->values();
    }

    protected function normalizeProductIds(array $raw): array
    {
        $max = max(1, (int) config('store.story_max_products', 10));

        $ids = collect($raw)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty() || $ids->count() > $max) {
            throw ValidationException::withMessages([
                'product_ids' => ["لازم تختار من منتج واحد إلى {$max} منتجات"],
            ]);
        }

        return $ids->all();
    }

    protected function assertQuotaAvailable(User $influencer): void
    {
        $quota = $this->quota($influencer);

        if ($quota['remaining'] <= 0) {
            throw ValidationException::withMessages([
                'story' => ["وصلت للحد الأقصى ({$quota['limit']}) قصص كل {$quota['window_hours']} ساعة"],
            ]);
        }
    }

    /** كل المنتجات لازم تكون مربوطة بالمؤثر وربطها مفعّل والمنتج نفسه مفعّل */
    protected function assertProductsAllowed(User $influencer, array $productIds): void
    {
        $allowed = $influencer->influencerProducts()
            ->wherePivot('is_active', true)
            ->where('products.is_active', true)
            ->whereIn('products.id', $productIds)
            ->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count(array_diff($productIds, $allowed)) > 0) {
            throw ValidationException::withMessages([
                'product_ids' => ['منتج أو أكتر مش مربوط بحسابك أو مش متاح حاليًا'],
            ]);
        }
    }

    /** رابط الترويج الفعّال للمنتج ده، أو ينشئ واحد جديد */
    protected function linkFor(User $influencer, int $productId): PromotionalLink
    {
        $existing = PromotionalLink::query()
            ->where('influencer_id', $influencer->id)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();

        return $existing ?? $this->links->generate($influencer, $productId);
    }

    /** @return array<int> */
    protected function idsFor(string $table, int $userId, array $storyIds): array
    {
        if ($storyIds === []) {
            return [];
        }

        return DB::table($table)
            ->where('user_id', $userId)
            ->whereIn('story_id', $storyIds)
            ->pluck('story_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}