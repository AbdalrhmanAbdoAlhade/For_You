<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CreatorResource;
use App\Http\Resources\ProductResource;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        protected SearchService $search,
    ) {}

    /** endpoint موحد — previews لكل نوع */
    public function unified(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'           => 'required|string|min:1|max:100',
            'type'        => 'nullable|in:all,products,brands,creators',
            'brand_id'    => 'nullable|integer|exists:brands,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'featured'    => 'nullable|boolean',
            'min_price'   => 'nullable|numeric|min:0',
            'max_price'   => 'nullable|numeric|min:0',
            'sort'        => 'nullable|in:best_match,popular,price_asc,price_desc,newest,oldest',
        ]);

        $q = trim($data['q']);
        $type = $data['type'] ?? 'all';
        $filters = collect($data)->except(['q', 'type'])->filter(fn ($v) => $v !== null)->all();

        $result = $this->search->searchAll($q, $filters, $type);

        // نسجّل البحث
        $this->search->record($q, $request->user(), $type, $result['total']);

        return response()->json([
            'query'    => $q,
            'products' => $result['products'],
            'brands'   => $result['brands'],
            'creators' => $result['creators'],
        ]);
    }

    /** products مع pagination كامل */
    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'           => 'required|string|min:1|max:100',
            'brand_id'    => 'nullable|integer|exists:brands,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'featured'    => 'nullable|boolean',
            'min_price'   => 'nullable|numeric|min:0',
            'max_price'   => 'nullable|numeric|min:0',
            'sort'        => 'nullable|in:best_match,popular,price_asc,price_desc,newest,oldest',
            'per_page'    => 'nullable|integer|min:1|max:50',
        ]);

        $q = trim($data['q']);
        $perPage = (int) ($data['per_page'] ?? 15);
        $filters = collect($data)->except(['q', 'per_page'])->filter(fn ($v) => $v !== null)->all();

        $paginator = $this->search->searchProducts($q, $filters, $perPage);
        $this->search->record($q, $request->user(), 'products', $paginator->total());

        return response()->json([
            'data' => ProductResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /** brands مع pagination */
    public function brands(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'        => 'required|string|min:1|max:100',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $q = trim($data['q']);
        $paginator = $this->search->searchBrands($q, (int) ($data['per_page'] ?? 15));
        $this->search->record($q, $request->user(), 'brands', $paginator->total());

        return response()->json([
            'data' => BrandResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /** creators مع pagination */
    public function creators(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'        => 'required|string|min:1|max:100',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $q = trim($data['q']);
        $paginator = $this->search->searchCreators($q, (int) ($data['per_page'] ?? 15));
        $this->search->record($q, $request->user(), 'creators', $paginator->total());

        return response()->json([
            'data' => CreatorResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /** آخر 10 searches للمستخدم (auth إجباري) */
    public function recent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $recent = $this->search->recentFor($request->user(), (int) ($data['limit'] ?? 10));

        return response()->json(['data' => $recent]);
    }

    /** trending global (public + cache) */
    public function trending(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $trending = $this->search->trending((int) ($data['limit'] ?? 10));

        return response()->json(['data' => $trending]);
    }
}
