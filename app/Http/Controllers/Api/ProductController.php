<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /** variants المنتج للـ public (product details) */
    public function variants(Product $product)
    {
        if (!$product->is_active) {
            abort(404);
        }

        return ProductVariantResource::collection(
            $product->variants()->active()->ordered()->get()
        );
    }

  public function index(Request $request)
{
    $filters = $request->validate([
        'search'      => 'nullable|string|max:100',
        'category_id' => 'nullable|integer|exists:categories,id',
        'brand_id'    => 'nullable|integer|exists:brands,id',
        'featured'    => 'nullable|boolean',
        'min_price'   => 'nullable|numeric|min:0',
        'max_price'   => 'nullable|numeric|min:0',
        'sort'        => 'nullable|in:best_match,popular,price_asc,price_desc,newest,oldest',
        'per_page'    => 'nullable|integer|min:1',
    ]);

    $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);
    $search = $filters['search'] ?? null;

    $products = Product::active()
        ->withCount('variants')
        ->with('category')
        ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
        ->when($filters['brand_id'] ?? null, fn ($q, $id) => $q->where('brand_id', $id))
        ->when($search, function ($q) use ($search) {
            $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('description_en', 'like', "%{$search}%");
            });
        })
        ->when(
            ($filters['featured'] ?? false) === true,
            fn ($q) => $q->featured()
        )
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

    // ترتيب
    $sort = $filters['sort'] ?? ($search ? 'best_match' : 'newest');

    match ($sort) {
        'best_match' => $search
            ? $this->applyBestMatchSort($products, $search)
            : $products->orderByDesc('reviews_count')->orderByDesc('rating_avg'),
        'popular' => $products->orderByDesc('reviews_count')->orderByDesc('rating_avg'),
        'price_asc' => $products->orderByRaw(
            'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) ASC'
        ),
        'price_desc' => $products->orderByRaw(
            'COALESCE(CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE NULL END, price) DESC'
        ),
        'newest' => $products->latest(),
        'oldest' => $products->oldest(),
        default  => $products->latest(),
    };

    return ProductResource::collection($products->paginate($perPage));
}

/** best_match: relevance scoring (نفس منطق SearchService) */
protected function applyBestMatchSort($query, string $q): void
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
    ->orderByDesc('reviews_count');
}

public function show(Product $product, Request $request)
{
    $isStaff = $request->user()
        && in_array($request->user()->type, ['admin', 'employee'], true);

    // Public: inactive product = 404. Staff: allowed (existing admin behavior preserved)
    abort_if(!$product->is_active && !$isStaff, 404);

    // Public: only active variants. Staff: all variants (for admin/editor review)
    $product->load([
        'category',
        'brand',
        'variants' => fn ($q) => $isStaff ? $q : $q->where('is_active', true),
    ]);

    // styled_by: DB::table مباشرة (مثبت شغال)
    $creator = \DB::table('influencer_product')
        ->join('users', 'users.id', '=', 'influencer_product.influencer_id')
        ->leftJoin('influencer_profiles', 'influencer_profiles.user_id', '=', 'users.id')
        ->where('influencer_product.product_id', $product->id)
        ->where('influencer_product.is_active', true)
        ->where('users.type', 'influencer')
        ->where('users.is_active', true)
        ->whereNull('users.deleted_at')
        ->select('users.id', 'users.name', 'users.avatar', 'influencer_profiles.handle')
        ->orderBy('influencer_product.id')
        ->first();

    $styledBy = $creator ? [
        'id'     => (int) $creator->id,
        'name'   => (string) $creator->name,
        'avatar' => $creator->avatar,
        'handle' => $creator->handle,
    ] : null;

    // variants كـ array بسيط
    $variants = [];
    foreach ($product->variants as $v) {
        $variants[] = [
            'id'             => $v->id,
            'size'           => $v->size,
            'color'          => $v->color,
            'label'          => $v->label,
            'sku'            => $v->sku,
            'price'          => $v->price !== null ? (float) $v->price : null,
            'discount_price' => $v->discount_price !== null ? (float) $v->discount_price : null,
            'in_stock'       => (bool) $v->is_in_stock,
            'is_active'      => (bool) $v->is_active,   // ← جديد: يظهر للـ staff
            'sort_order'     => (int) $v->sort_order,
        ];
    }

    $category = $product->category ? [
        'id'   => $product->category->id,
        'name' => $product->category->localized('name'),
        'slug' => $product->category->slug,
    ] : null;

    $brand = ($product->brand && ($isStaff || $product->brand->is_active)) ? [
    'id'   => $product->brand->id,
    'name' => $product->brand->localized('name'),
    'slug' => $product->brand->slug,
    'logo' => $product->brand->logo,
] : null;

    return response()->json(['data' => [
        'id'                => $product->id,
        'name'              => $product->localized('name'),
        'slug'              => $product->slug,
        'description'       => $product->localized('description'),
        'short_description' => $product->localized('short_description'),
        'price'             => (float) $product->price,
        'discount_price'    => $product->has_discount ? (float) $product->effective_price : null,
        'final_price'       => (float) $product->final_price,
        'rating_avg'        => (float) $product->rating_avg,
        'reviews_count'     => (int) $product->reviews_count,
        'has_variants'      => count($variants) > 0,
        'variants'          => $variants,
        'in_stock'          => $product->stock > 0,
        'images'            => $product->images ?? [],
        'is_featured'       => (bool) $product->is_featured,
        'category'          => $category,
        'brand'             => $brand,
        'styled_by'         => $styledBy,
        'created_at'        => $product->created_at?->toDateTimeString(),
    ]]);
}
  /** GET /api/products/{id}/related — "Complete the look" */
public function related(Request $request, Product $product)
{
    $data = $request->validate([
        'limit' => 'nullable|integer|min:1|max:12',
    ]);

    $limit = (int) ($data['limit'] ?? 8);

    // لو مفيش brand ولا category → مفيش اقتراحات ممكنة
    if (!$product->brand_id && !$product->category_id) {
        return ProductResource::collection(collect());
    }

    $query = Product::query()
        ->active()
        ->where('id', '!=', $product->id)
        ->with('category')
        ->withCount('variants')
        ->where(function ($q) use ($product) {
            if ($product->brand_id) {
                $q->orWhere('brand_id', $product->brand_id);
            }
            if ($product->category_id) {
                $q->orWhere('category_id', $product->category_id);
            }
        });

    // الأولوية: نفس البراند، بعدين التقييم، بعدين المراجعات
    if ($product->brand_id) {
        $query->orderByRaw('CASE WHEN brand_id = ? THEN 0 ELSE 1 END', [$product->brand_id]);
    }

    $query->orderByDesc('rating_avg')
          ->orderByDesc('reviews_count')
          ->orderByDesc('id')
          ->limit($limit);

    return ProductResource::collection($query->get());
}
    public function featured()
    {
        $products = Product::active()
            ->featured()
            ->withCount('variants')
            ->with('category')
            ->latest()
            ->take(12)
            ->get();

        return ProductResource::collection($products);
    }
}