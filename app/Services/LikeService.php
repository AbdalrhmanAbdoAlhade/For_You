<?php

namespace App\Services;

use App\Models\InfluencerStory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LikeService
{
    // ---------------- Products (wishlist) ----------------

    /**
     * @return array{liked:bool, likes_count:int}
     */
    public function likeProduct(User $user, Product $product): array
    {
        if (!$product->is_active) {
            throw ValidationException::withMessages([
                'product' => ['المنتج ده مش متاح'],
            ]);
        }

        DB::table('product_likes')->insertOrIgnore([
            'user_id'    => $user->id,
            'product_id' => $product->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->productState($product, true);
    }

    /**
     * @return array{liked:bool, likes_count:int}
     */
    public function unlikeProduct(User $user, Product $product): array
    {
        DB::table('product_likes')
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->delete();

        return $this->productState($product, false);
    }

    /** قائمة المفضلة (المنتجات النشطة فقط، الأحدث إضافة الأول) */
    public function wishlist(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->likedProducts()
            ->where('products.is_active', true)
            ->with(['category', 'brand'])
            ->orderByPivot('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * أيهم من المنتجات دي المستخدم عامل لها لايك (لعلامة القلب في القوائم).
     *
     * @param  array<int> $productIds
     * @return array<int>
     */
    public function likedProductIds(User $user, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return DB::table('product_likes')
            ->where('user_id', $user->id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ---------------- Stories ----------------

    /**
     * @return array{liked:bool, likes_count:int}
     */
    public function likeStory(User $user, InfluencerStory $story): array
    {
        if (!$story->isLive()) {
            throw ValidationException::withMessages([
                'story' => ['القصة دي مش متاحة'],
            ]);
        }

        DB::table('influencer_story_likes')->insertOrIgnore([
            'user_id'    => $user->id,
            'story_id'   => $story->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->storyState($story, true);
    }

    /**
     * @return array{liked:bool, likes_count:int}
     */
    public function unlikeStory(User $user, InfluencerStory $story): array
    {
        DB::table('influencer_story_likes')
            ->where('user_id', $user->id)
            ->where('story_id', $story->id)
            ->delete();

        return $this->storyState($story, false);
    }

    // ---------------- Internals ----------------

    protected function productState(Product $product, bool $liked): array
    {
        return [
            'liked'       => $liked,
            'likes_count' => (int) DB::table('product_likes')->where('product_id', $product->id)->count(),
        ];
    }

    protected function storyState(InfluencerStory $story, bool $liked): array
    {
        return [
            'liked'       => $liked,
            'likes_count' => (int) DB::table('influencer_story_likes')->where('story_id', $story->id)->count(),
        ];
    }
}