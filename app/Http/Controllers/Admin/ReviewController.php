<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\ProductReview;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviews,
    ) {}

    /** GET /admin/reviews */
    public function index(Request $request)
    {
        $query = ProductReview::query()
            ->with(['user:id,name,avatar,type', 'images', 'product:id,name,name_en,images,brand_id'])
            ->latest('id');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return ReviewResource::collection($query->paginate($perPage));
    }

    /** PATCH /admin/reviews/{review}/active */
    public function setActive(Request $request, ProductReview $review)
    {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $review = $this->reviews->setActive($review, (bool) $request->is_active);

        return new ReviewResource($review);
    }

    /** DELETE /admin/reviews/{review} */
    public function destroy(ProductReview $review)
    {
        $this->reviews->delete($review);

        return response()->json(null, 204);
    }
}