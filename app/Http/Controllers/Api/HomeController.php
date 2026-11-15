<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /** بادئة مفتاح كاش الصفحة الرئيسية (بيضاف لها اللغة). */
    public const CACHE_KEY = 'api.home.payload';

    /** اللغات المدعومة (لازم تطابق SetLocale). */
    protected const LOCALES = ['ar', 'en'];

    /** مدة الكاش بالثواني (5 دقائق). */
    protected const CACHE_TTL = 300;

    /** مفتاح الكاش للغة معينة. */
    protected static function cacheKey(string $locale): string
    {
        return self::CACHE_KEY . ':' . $locale;
    }

    /** يمسح كاش الصفحة الرئيسية لكل اللغات. */
    public static function flush(): void
    {
        foreach (self::LOCALES as $locale) {
            Cache::forget(self::cacheKey($locale));
        }

        // المفتاح القديم (قبل فصل اللغات) لو كان لسه متخزن.
        Cache::forget(self::CACHE_KEY);
    }

    public function index()
    {
        // العناوين والأسماء بتتغير حسب Accept-Language، فالكاش لازم يكون لكل لغة.
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
        ];
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
        // الأكبر خصماً الأول
        return $this->base()->onSale()->orderByRaw('(price - discount_price) / price DESC');
    }
}