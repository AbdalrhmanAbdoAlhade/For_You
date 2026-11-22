<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviews,
    ) {}

    // ------------------------------------------------------------------
    // قراءة
    // ------------------------------------------------------------------

    /** GET /products/{product}/reviews */
    public function productIndex(Request $request, Product $product)
    {
        $filters = $request->only(['filter', 'rating']);
        $perPage = min((int) $request->get('per_page', 10), 50);

        $paginator = $this->reviews->listForProduct($product, $filters, $perPage);

        return ReviewResource::collection($paginator);
    }

    /** GET /products/{product}/reviews/summary */
    public function productSummary(Product $product)
    {
        return response()->json($this->reviews->summaryForProduct($product));
    }

    /** GET /brands/{brand}/reviews */
    public function brandIndex(Request $request, Brand $brand)
    {
        $filters = $request->only(['filter', 'rating']);
        $perPage = min((int) $request->get('per_page', 10), 50);

        $paginator = $this->reviews->listForBrand($brand, $filters, $perPage);

        return ReviewResource::collection($paginator);
    }

    /** GET /brands/{brand}/reviews/summary */
    public function brandSummary(Brand $brand)
    {
        return response()->json($this->reviews->summaryForBrand($brand));
    }

    /** GET /influencers/{user}/reviews  (أو users/{user}/reviews) */
  public function userIndex(Request $request, User $user)
{
    // Only active influencers can have public review pages
    abort_if($user->type !== 'influencer' || !$user->is_active, 404);

    $perPage = min((int) $request->get('per_page', 10), 50);

    $paginator = $this->reviews->listByUser($user, $perPage);

    return ReviewResource::collection($paginator);
}

    /** GET /products/{product}/review-state  (للزرار Rate & review) */
    public function state(Request $request, Product $product)
    {
        return response()->json(
            $this->reviews->stateFor($request->user(), $product)
        );
    }

    // ------------------------------------------------------------------
    // كتابة
    // ------------------------------------------------------------------

    /** POST /products/{product}/reviews */
    public function store(StoreReviewRequest $request, Product $product)
    {
        $review = $this->reviews->create(
            $request->user(),
            $product,
            $request->validated(),
            $request->file('images', [])
        );

        return (new ReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }

    /** PUT/PATCH /reviews/{review} */
    public function update(UpdateReviewRequest $request, ProductReview $review)
    {
        $this->authorizeOwner($request, $review);

        $data = $request->validated();

        $review = $this->reviews->update(
            $review,
            $data,
            $request->file('images', []),
            $data['remove_image_ids'] ?? []
        );

        return new ReviewResource($review);
    }

    /** DELETE /reviews/{review} */
    public function destroy(Request $request, ProductReview $review)
    {
        $this->authorizeOwner($request, $review);

        $this->reviews->delete($review);

        return response()->json(null, 204);
    }

    // ------------------------------------------------------------------

    protected function authorizeOwner(Request $request, ProductReview $review): void
    {
        if ($review->user_id !== $request->user()->id) {
            abort(403, 'مش مسموح تعدّل تقييم مش بتاعك');
        }
    }
}