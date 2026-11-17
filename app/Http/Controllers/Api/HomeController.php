<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StoryRailResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\InfluencerStoryService;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public const CACHE_KEY = 'api.home.payload';
    protected const LOCALES = ['ar', 'en'];
    protected const CACHE_TTL = 300;
    protected const TRENDING_LIMIT = 12;

    protected static function cacheKey(string $locale): string
    {
        return self::CACHE_KEY . ':' . $locale;
    }

    public static function flush(): void
    {
        foreach (self::LOCALES as $locale) {
            Cache::forget(self::cacheKey($locale));
        }
        Cache::forget(self::CACHE_KEY);
    }

    public function index(): JsonResponse
    {
        $locale = app()->getLocale();

        $payload = Cache::remember(
            self::cacheKey($locale),
            self::CACHE_TTL,
            fn () => $this->payload()
        );

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $categories = Category::active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return [
            'banners' => Banner::active()->orderBy('sort_order')->get()->map(fn (Banner $b) => [
                'id'          => $b->id,
                'title'       => $b->localized('title'),
                'subtitle'    => $b->localized('subtitle'),
                'image'       => $b->image,
                'link_url'    => $b->link_url,
                'product_id'  => $b->product_id,
                'category_id' => $b->category_id,
            ])->values()->all(),

            'categories'       => CategoryResource::collection($categories)->resolve(),
            'featured'         => ProductResource::collection($this->base()->featured()->latest()->take(12)->get())->resolve(),
            'new_arrivals'     => ProductResource::collection($this->base()->latest()->take(12)->get())->resolve(),
            'offers'           => ProductResource::collection($this->offersQuery()->take(12)->get())->resolve(),
            'influencer_picks' => ProductResource::collection(
                $this->base()
                    ->whereHas('influencers', function ($q) {
                        $q->where('users.is_active', true)->where('influencer_product.is_active', true);
                    })
                    ->latest()
                    ->take(12)
                    ->get()
            )->resolve(),

            // جديد: rail القصص (نفس منطق StoryController::discover — للزائر)
            'stories' => $this->storiesRail(),

            // جديد: منتجات trending (global — مش personalized لضمان cache صح)
            'trending' => $this->trendingProducts(),
        ];
    }

    /** Rails القصص النشطة (discover logic للزائر — global) */
    protected function storiesRail(): array
    {
        try {
            $stories = app(InfluencerStoryService::class)->discoverRail(null);
            return StoryRailResource::collection($stories)->resolve();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /** trending products (global, مش user-based) */
    protected function trendingProducts(): array
    {
        try {
            $result = app(RecommendationService::class)->trendingFor(null, self::TRENDING_LIMIT);
            return [
                'products'       => ProductResource::collection($result['products'])->resolve(),
                'based_on_likes' => (bool) ($result['based_on_likes'] ?? false),
            ];
        } catch (\Throwable $e) {
            report($e);
            return ['products' => [], 'based_on_likes' => false];
        }
    }

    public function offers()
    {
        return ProductResource::collection($this->offersQuery()->paginate(15));
    }

    public function newArrivals()
    {
        return ProductResource::collection($this->base()->latest()->paginate(15));
    }

    protected function base()
    {
        return Product::active()->inStock()->with('category');
    }

    protected function offersQuery()
    {
        return $this->base()->onSale()->orderByRaw('(price - discount_price) / price DESC');
    }
}
