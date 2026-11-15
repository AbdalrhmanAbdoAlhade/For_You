<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\LikeService;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(
        protected LikeService $likes
    ) {}

    /** GET wishlist: المنتجات اللي عملت لها لايك */
    public function index(Request $request)
    {
        $request->validate(['per_page' => 'nullable|integer|min:1']);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        return ProductResource::collection($this->likes->wishlist($request->user(), $perPage));
    }

    /**
     * GET wishlist/ids: أرقام المنتجات المعجب بيها بس.
     * الفرونت بيستخدمها لتلوين القلب على القوائم، لأن الهوم متخزن في كاش مشترك ومفيهوش بيانات شخصية.
     */
    public function ids(Request $request)
    {
        return response()->json([
            'product_ids' => $request->user()->likedProducts()
                ->pluck('products.id')
                ->map(fn ($id) => (int) $id)
                ->values(),
        ]);
    }

    /** POST products/{id}/like */
    public function like(Request $request, int $id)
    {
        $product = Product::active()->findOrFail($id);

        return response()->json($this->likes->likeProduct($request->user(), $product));
    }

    /** DELETE products/{id}/like */
    public function unlike(Request $request, int $id)
    {
        $product = Product::withTrashed()->findOrFail($id);

        return response()->json($this->likes->unlikeProduct($request->user(), $product));
    }
}