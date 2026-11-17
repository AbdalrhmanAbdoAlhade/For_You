<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchService
{
    public const MAX_PER_PAGE = 50;
    public const PREVIEW_LIMIT = 5;
    public const TRENDING_CACHE_TTL = 1800; // 30 min
    public const TRENDING_DAYS = 7;

    // ============================================================
    // Products
    // ============================================================

    public function searchProducts(string $q, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), self::MAX_PER_PAGE);

        $query = Product::active()
            ->with('category')
            ->withCount('variants')
            ->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%")
                    ->orWhere('short_description', 'like', "%{$q}%")
                    ->orWhere('short_description_en', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('description_en', 'like', "%{$q}%");
            });

        $this->applyProductFilters($query, $filters);
        $this->applyProductSort($query, $q, $filters['sort'] ?? 'best_match');

        return $query->paginate($perPage);
    }

    // ============================================================
    // Brands
    // ============================================================

    public function searchBrands(string $q, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), self::MAX_PER_PAGE);

        return Brand::query()
            ->where('is_active', true)
            ->withCount('products')
            ->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('description_en', 'like', "%{$q}%");
            })
            ->orderByRaw(
                "CASE
                    WHEN name LIKE ? THEN 3
                    WHEN name LIKE ? THEN 2
                    ELSE 1
                END DESC",
                ["{$q}%", "%{$q}%"]
            )
            ->orderByDesc('reviews_count')
            ->paginate($perPage);
    }

    // ============================================================
    // Creators
    // ============================================================

    public function searchCreators(string $q, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), self::MAX_PER_PAGE);

        return User::query()
            ->where('type', 'influencer')
            ->where('is_active', true)
            ->where('name', 'like', "%{$q}%")
            ->select('users.*')
            ->selectSub($this->followersSubquery(), 'followers_count')
            ->selectSub($this->followingSubquery(), 'following_count')
            ->orderByRaw(
                "CASE
                    WHEN name LIKE ? THEN 3
                    WHEN name LIKE ? THEN 2
                    ELSE 1
                END DESC",
                ["{$q}%", "%{$q}%"]
            )
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM influencer_follows WHERE influencer_id = users.id)'))
            ->orderBy('id')
            ->paginate($perPage);
    }

    protected function followersSubquery()
    {
        return DB::table('influencer_follows')
            ->selectRaw('count(*)')
            ->whereColumn('influencer_id', 'users.id');
    }

    protected function followingSubquery()
    {
        return DB::table('influencer_follows')
            ->selectRaw('count(*)')
            ->whereColumn('user_id', 'users.id');
    }

    // ============================================================
    // Unified (previews only)
    // ============================================================

    /**
     * @return array{products: array, brands: array, creators: array, total: int}
     */
    public function searchAll(string $q, array $filters = [], string $type = 'all'): array
    {
        $includeProducts = in_array($type, ['all', 'products'], true);
        $includeBrands   = in_array($type, ['all', 'brands'], true);
        $includeCreators = in_array($type, ['all', 'creators'], true);

        $products = $brands = $creators = null;
        $total = 0;

        if ($includeProducts) {
            $p = $this->searchProducts($q, $filters, self::PREVIEW_LIMIT);
            $products = [
                'data'  => \App\Http\Resources\ProductResource::collection($p->items()),
                'total' => $p->total(),
            ];
            $total += $p->total();
        }

        if ($includeBrands) {
            $b = $this->searchBrands($q, self::PREVIEW_LIMIT);
            $brands = [
                'data'  => \App\Http\Resources\BrandResource::collection($b->items()),
                'total' => $b->total(),
            ];
            $total += $b->total();
        }

        if ($includeCreators) {
            $c = $this->searchCreators($q, self::PREVIEW_LIMIT);
            $creators = [
                'data'  => \App\Http\Resources\CreatorResource::collection($c->items()),
                'total' => $c->total(),
            ];
            $total += $c->total();
        }

        return [
            'products' => $products,
            'brands'   => $brands,
            'creators' => $creators,
            'total'    => $total,
        ];
    }

    // ============================================================
    // History
    // ============================================================

    /**
     * يسجّل البحث. بيتنادى من SearchController بعد ما يتعرف results_count.
     */
    public function record(string $q, ?User $user, string $type, int $resultsCount): void
    {
        SearchHistory::create([
            'user_id'       => $user?->id,
            'query'         => mb_substr(trim($q), 0, 100),
            'type'          => $type,
            'results_count' => max(0, $resultsCount),
        ]);
    }

    /**
     * آخر 10 searches للمستخدم (distinct query، الأحدث أولاً).
     * بنستثني اللي results_count = 0 عشان ما نزعجش اليوزر.
     */
    public function recentFor(User $user, int $limit = 10): Collection
    {
        return SearchHistory::query()
            ->where('user_id', $user->id)
            ->where('results_count', '>', 0)
            ->select('query', DB::raw('MAX(created_at) as last_searched_at'))
            ->groupBy('query')
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'query'            => $r->query,
                'last_searched_at' => $r->last_searched_at,
            ]);
    }

    /**
     * Trending: أكثر الكلمات بحثاً آخر 7 أيام (مع كاش 30 دقيقة).
     * بنستخدم كل searches (حتى بدون نتائج) لأن ده بيعكس اهتمام حقيقي.
     */
    public function trending(int $limit = 10): Collection
    {
        return Cache::remember(
            'search.trending.v1.' . $limit,
            self::TRENDING_CACHE_TTL,
            function () use ($limit) {
                return SearchHistory::query()
                    ->where('created_at', '>=', now()->subDays(self::TRENDING_DAYS))
                    ->select('query', DB::raw('COUNT(*) as searches_count'))
                    ->groupBy('query')
                    ->orderByDesc('searches_count')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    public function flushTrendingCache(): void
    {
        // مفيش wildcard delete في Cache Facade، بس نقدر نستخدم pattern match على Redis
        // في أبسط حل: نسيب الـ TTL ينتهي (30 دقيقة)، والـ trending بيتحدث تلقائياً
        // لو محتاج flush فوري، استخدم Cache::forget('search.trending.v1.10') إلخ
        Cache::forget('search.trending.v1.10');
    }

    // ============================================================
    // Helpers
    // ============================================================

    protected function applyProductFilters($query, array $filters): void
    {
        $query
            ->when(!empty($filters['brand_id']), fn ($q) => $q->where('brand_id', $filters['brand_id']))
            ->when(!empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(!empty($filters['featured']), fn ($q) => $q->where('is_featured', true))
            ->when(isset($filters['min_price']), function ($q) use ($filters) {
                $q->whereRaw(
                    'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) >= ?',
                    [(float) $filters['min_price']]
                );
            })
            ->when(isset($filters['max_price']), function ($q) use ($filters) {
                $q->whereRaw(
                    'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) <= ?',
                    [(float) $filters['max_price']]
                );
            });
    }

    protected function applyProductSort($query, string $q, string $sort): void
    {
        match ($sort) {
            'popular' => $query->orderByDesc('reviews_count')->orderByDesc('rating_avg')->orderByDesc('id'),
            'price_asc' => $query->orderByRaw(
                'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) ASC'
            ),
            'price_desc' => $query->orderByRaw(
                'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) DESC'
            ),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            default => $this->applyBestMatch($query, $q),
        };
    }

    protected function applyBestMatch($query, string $q): void
    {
        $query->orderByRaw(
            "CASE
                WHEN name LIKE ? OR name_en LIKE ? THEN 3
                WHEN name LIKE ? OR name_en LIKE ? THEN 2
                WHEN short_description LIKE ? OR description LIKE ? THEN 1
                ELSE 0
            END DESC",
            ["{$q}%", "{$q}%", "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%"]
        )
        ->orderByDesc('rating_avg')
        ->orderByDesc('reviews_count')
        ->orderByDesc('id');
    }
}
