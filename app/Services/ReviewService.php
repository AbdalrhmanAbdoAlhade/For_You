<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /** أقصى عدد صور للتقييم الواحد */
    public const MAX_IMAGES = 3;

    protected const EAGER = ['user:id,name,avatar,type', 'images'];

    /** المنتج اللي بيظهر تحت التقييم في صفحات البراند وبروفايل المؤثر */
    protected const PRODUCT_EAGER = 'product:id,name,name_en,images,brand_id';

    public function __construct(
        protected FileUploadService $uploads,
    ) {}

    // ======================================================================
    // كتابة / تعديل / حذف
    // ======================================================================

    /**
     * @param array{rating:int, body?:?string} $data
     * @param array<int, UploadedFile>         $images
     */
    public function create(User $user, Product $product, array $data, array $images = []): ProductReview
    {
        $this->assertProductReviewable($product);
        $this->assertImagesCount(count($images));

        // فحص مبدئي قبل الرفع، عشان منعالجش صور لطلب هيترفض
        $this->assertNotReviewed($user, $product);

        $urls = $images === [] ? [] : $this->uploads->uploadMultiple($images, 'reviews');

        try {
            return DB::transaction(function () use ($user, $product, $data, $urls) {
                // قفل صف المستخدم: طلبين متزامنين ميعدّوش التكرار
                User::query()->whereKey($user->id)->lockForUpdate()->first();

                $this->assertNotReviewed($user, $product);

                $orderId = $this->purchaseOrderId($user, $product);

                $review = ProductReview::create([
                    'user_id'              => $user->id,
                    'product_id'           => $product->id,
                    'order_id'             => $orderId,
                    'rating'               => (int) $data['rating'],
                    'body'                 => $data['body'] ?? null,
                    'is_verified_purchase' => $orderId !== null,
                    'is_creator'           => $user->type === 'influencer',
                    'is_active'            => true,
                ]);

                foreach ($urls as $index => $url) {
                    $review->images()->create([
                        'image'      => $url,
                        'sort_order' => $index,
                    ]);
                }

                $this->recalculate($product->id);

                return $review->load(self::EAGER);
            });
        } catch (\Throwable $e) {
            foreach ($urls as $url) {
                $this->uploads->delete($url);
            }

            throw $e;
        }
    }

    /**
     * تعديل تقييم (صاحبه بس، والتحقق من الملكية في الـ Controller).
     * أي حقل مش مبعوت بيفضل زي ما هو.
     *
     * @param array{rating?:int, body?:?string} $data
     * @param array<int, UploadedFile>          $newImages
     * @param array<int, int>                   $removeImageIds
     */
    public function update(
        ProductReview $review,
        array $data,
        array $newImages = [],
        array $removeImageIds = []
    ): ProductReview {
        $removeImageIds = array_values(array_unique(array_map('intval', $removeImageIds)));

        $current   = $review->images()->get();
        $toRemove  = $current->whereIn('id', $removeImageIds);
        $remaining = $current->count() - $toRemove->count();

        $this->assertImagesCount($remaining + count($newImages));

        $newUrls = $newImages === [] ? [] : $this->uploads->uploadMultiple($newImages, 'reviews');

        try {
            $removedUrls = DB::transaction(function () use ($review, $data, $current, $toRemove, $newUrls) {
                $payload = [];

                if (array_key_exists('rating', $data)) {
                    $payload['rating'] = (int) $data['rating'];
                }

                if (array_key_exists('body', $data)) {
                    $payload['body'] = $data['body'];
                }

                if ($payload !== []) {
                    $review->update($payload);
                }

                if ($toRemove->isNotEmpty()) {
                    $review->images()->whereIn('id', $toRemove->pluck('id')->all())->delete();
                }

                $start = $current->isEmpty() ? 0 : ((int) $current->max('sort_order') + 1);

                foreach ($newUrls as $index => $url) {
                    $review->images()->create([
                        'image'      => $url,
                        'sort_order' => $start + $index,
                    ]);
                }

                $this->recalculate($review->product_id);

                return $toRemove->pluck('image')->all();
            });
        } catch (\Throwable $e) {
            foreach ($newUrls as $url) {
                $this->uploads->delete($url);
            }

            throw $e;
        }

        // الملفات القديمة بتتمسح بعد نجاح الـ commit
        foreach ($removedUrls as $url) {
            $this->uploads->delete($url);
        }

        return $review->fresh(self::EAGER);
    }

    /** حذف تقييم نهائي مع صوره (الصور في الـ DB بتتمسح بالـ cascade) */
    public function delete(ProductReview $review): void
    {
        $urls      = $review->images()->pluck('image')->all();
        $productId = $review->product_id;

        DB::transaction(function () use ($review, $productId) {
            $review->delete();

            $this->recalculate($productId);
        });

        foreach ($urls as $url) {
            $this->uploads->delete($url);
        }
    }

    /** إخفاء/إظهار تقييم (الأدمن). المخفي بيتشال من المتوسط والعدد. */
    public function setActive(ProductReview $review, bool $active): ProductReview
    {
        DB::transaction(function () use ($review, $active) {
            $review->update(['is_active' => $active]);

            $this->recalculate($review->product_id);
        });

        return $review->load(self::EAGER);
    }

    // ======================================================================
    // قراءة
    // ======================================================================

    /**
     * تقييمات منتج.
     *
     * @param array{filter?:?string, rating?:?int} $filters filter = photos | creators
     */
    public function listForProduct(Product $product, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = ProductReview::query()
            ->active()
            ->where('product_id', $product->id);

        return $this->paginateFeed($query, $filters, $perPage);
    }

    /**
     * تقييمات كل منتجات البراند (brand profile reviews).
     * بنستخدم subquery بدل hasManyThrough عشان مفيش join يعمل أعمدة مكررة.
     *
     * @param array{filter?:?string, rating?:?int} $filters
     */
    public function listForBrand(Brand $brand, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = ProductReview::query()
            ->active()
            ->whereIn('product_id', Product::query()->where('brand_id', $brand->id)->select('id'));

        return $this->paginateFeed($query, $filters, $perPage, [self::PRODUCT_EAGER]);
    }

    /** التقييمات اللي كتبها مستخدم/مؤثر (tab Reviews في بروفايل الكريتور) */
    public function listByUser(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return ProductReview::query()
            ->active()
            ->where('user_id', $user->id)
            ->with(array_merge(self::EAGER, [self::PRODUCT_EAGER]))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * ملخص التقييمات لمنتج: المتوسط والعدد وتوزيع النجوم (5 → 1).
     *
     * @return array{average:float, count:int, distribution:array<int, array{stars:int, count:int, percent:int}>}
     */
    public function summaryForProduct(Product $product): array
    {
        return $this->summarize(
            ProductReview::query()->active()->where('product_id', $product->id)
        );
    }

    /**
     * ملخص التقييمات لبراند (كل منتجاته).
     *
     * @return array{average:float, count:int, distribution:array<int, array{stars:int, count:int, percent:int}>}
     */
    public function summaryForBrand(Brand $brand): array
    {
        return $this->summarize(
            ProductReview::query()
                ->active()
                ->whereIn('product_id', Product::query()->where('brand_id', $brand->id)->select('id'))
        );
    }

    /**
     * حالة المستخدم مع المنتج ده: قيّمه قبل كده؟ وهل اشتراه فعلاً؟
     * (عشان زرار "Rate & review" في الـ UI)
     *
     * @return array{has_reviewed:bool, review_id:?int, is_verified_purchase:bool}
     */
    public function stateFor(User $user, Product $product): array
    {
        $mine = ProductReview::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->value('id');

        return [
            'has_reviewed'         => $mine !== null,
            'review_id'            => $mine !== null ? (int) $mine : null,
            'is_verified_purchase' => $this->purchaseOrderId($user, $product) !== null,
        ];
    }

    // ======================================================================
    // إعادة حساب المتوسطات
    // ======================================================================

    /**
     * يحدّث rating_avg و reviews_count للمنتج، وبعدها للبراند بتاعه.
     * بيستخدم query builder مباشر لأن الأعمدة دي مش في fillable.
     */
    public function recalculate(int $productId): void
    {
        $stats = DB::table('product_reviews')
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->selectRaw('COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average')
            ->first();

        DB::table('products')
            ->where('id', $productId)
            ->update([
                'rating_avg'    => round((float) $stats->average, 2),
                'reviews_count' => (int) $stats->total,
            ]);

        $brandId = DB::table('products')->where('id', $productId)->value('brand_id');

        if ($brandId) {
            $this->recalculateBrand((int) $brandId);
        }
    }

    /**
     * يحدّث متوسط وعدد تقييمات البراند من كل منتجاته (غير المحذوفة).
     * public عشان نناديه لما منتج يتنقل من براند لبراند تاني.
     */
    public function recalculateBrand(int $brandId): void
    {
        $stats = DB::table('product_reviews')
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->where('products.brand_id', $brandId)
            ->whereNull('products.deleted_at')
            ->where('product_reviews.is_active', true)
            ->selectRaw('COUNT(*) AS total, COALESCE(AVG(product_reviews.rating), 0) AS average')
            ->first();

        DB::table('brands')
            ->where('id', $brandId)
            ->update([
                'rating_avg'    => round((float) $stats->average, 2),
                'reviews_count' => (int) $stats->total,
            ]);
    }

    // ======================================================================
    // Helpers
    // ======================================================================

    /**
     * @param array{filter?:?string, rating?:?int} $filters
     * @param array<int, string>                   $extraWith
     */
    protected function paginateFeed(Builder $query, array $filters, int $perPage, array $extraWith = []): LengthAwarePaginator
    {
        $filter = $filters['filter'] ?? null;

        return $query
            ->with(array_merge(self::EAGER, $extraWith))
            ->when($filter === 'photos', fn (Builder $q) => $q->withPhotos())
            ->when($filter === 'creators', fn (Builder $q) => $q->fromCreators())
            ->when(isset($filters['rating']), fn (Builder $q) => $q->ofRating((int) $filters['rating']))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return array{average:float, count:int, distribution:array<int, array{stars:int, count:int, percent:int}>}
     */
    protected function summarize(Builder $query): array
    {
        $rows = (clone $query)
            ->reorder()
            ->select('rating', DB::raw('COUNT(*) AS total'))
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $count  = 0;
        $sum    = 0;
        $counts = [];

        foreach (range(5, 1) as $stars) {
            $n = (int) ($rows[$stars] ?? 0);

            $counts[$stars] = $n;
            $count += $n;
            $sum   += $stars * $n;
        }

        $distribution = [];

        foreach ($counts as $stars => $n) {
            $distribution[] = [
                'stars'   => $stars,
                'count'   => $n,
                'percent' => $count > 0 ? (int) round($n / $count * 100) : 0,
            ];
        }

        return [
            'average'      => $count > 0 ? round($sum / $count, 2) : 0.0,
            'count'        => $count,
            'distribution' => $distribution,
        ];
    }

    /** آخر أوردر متسلّم (delivered) للمستخدم فيه المنتج ده، أو null لو ما اشتراهوش */
    protected function purchaseOrderId(User $user, Product $product): ?int
    {
        $orderId = Order::query()
            ->where('customer_id', $user->id)
            ->whereIn('status', Order::SALE_STATUSES)
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->latest('id')
            ->value('id');

        return $orderId !== null ? (int) $orderId : null;
    }

    protected function assertProductReviewable(Product $product): void
    {
        if (!$product->is_active) {
            throw ValidationException::withMessages([
                'product' => ['المنتج ده مش متاح'],
            ]);
        }
    }

    protected function assertNotReviewed(User $user, Product $product): void
    {
        $exists = ProductReview::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'review' => ['إنت قيّمت المنتج ده قبل كده، تقدر تعدّل تقييمك'],
            ]);
        }
    }

    protected function assertImagesCount(int $count): void
    {
        if ($count > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => ['أقصى عدد صور للتقييم ' . self::MAX_IMAGES],
            ]);
        }
    }
}