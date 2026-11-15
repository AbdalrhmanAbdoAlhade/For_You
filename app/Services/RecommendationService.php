<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    /**
     * "Trending for you":
     * - بدون إعجابات: الأكثر إعجابًا ثم المميز ثم الأحدث.
     * - مع إعجابات: نفس كاتيجوري/براند المنتجات المعجب بيها بتتقدم، ومتابعات المستخدم
     *   من المؤثرين بتدي دفعة بسيطة، ومنتجاته المعجب بيها نفسها بتتستبعد.
     *
     * @return array{based_on_likes:bool, products:Collection}
     */
    public function trendingFor(?User $user, int $limit = 12): array
    {
        $limit = max(1, min($limit, 50));

        $query = Product::query()
            ->active()
            ->inStock()
            ->with(['category', 'brand'])
            ->withCount('likers');

        $scoreParts = [];
        $bindings   = [];
        $basedOnLikes = false;

        if ($user) {
            $likedIds = DB::table('product_likes')
                ->where('user_id', $user->id)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($likedIds !== []) {
                $basedOnLikes = true;

                $liked = DB::table('products')->whereIn('id', $likedIds)->get(['category_id', 'brand_id']);

                $categoryIds = $liked->pluck('category_id')->filter()->map(fn ($i) => (int) $i)->unique()->values()->all();
                $brandIds    = $liked->pluck('brand_id')->filter()->map(fn ($i) => (int) $i)->unique()->values()->all();

                if ($categoryIds !== []) {
                    $scoreParts[] = 'CASE WHEN products.category_id IN (' . $this->placeholders($categoryIds) . ') THEN 3 ELSE 0 END';
                    $bindings = array_merge($bindings, $categoryIds);
                }

                if ($brandIds !== []) {
                    $scoreParts[] = 'CASE WHEN products.brand_id IN (' . $this->placeholders($brandIds) . ') THEN 2 ELSE 0 END';
                    $bindings = array_merge($bindings, $brandIds);
                }

                $query->whereNotIn('products.id', $likedIds);
            }

            $followedIds = DB::table('influencer_follows')
                ->where('user_id', $user->id)
                ->pluck('influencer_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($followedIds !== []) {
                $scoreParts[] = 'CASE WHEN products.id IN ('
                    . 'SELECT ip.product_id FROM influencer_product ip '
                    . 'WHERE ip.is_active = 1 AND ip.influencer_id IN (' . $this->placeholders($followedIds) . ')'
                    . ') THEN 1 ELSE 0 END';
                $bindings = array_merge($bindings, $followedIds);
            }
        }

        if ($scoreParts !== []) {
            $query->orderByRaw('(' . implode(' + ', $scoreParts) . ') DESC', $bindings);
        }

        $products = $query
            ->orderByDesc('likers_count')
            ->orderByDesc('products.is_featured')
            ->orderByDesc('products.created_at')
            ->limit($limit)
            ->get();

        return [
            'based_on_likes' => $basedOnLikes,
            'products'       => $products,
        ];
    }

    /** @param array<int> $ids */
    protected function placeholders(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}