<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $categories = Category::active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'banners' => Banner::active()
                ->orderBy('sort_order')
                ->get(['id', 'title', 'subtitle', 'image', 'link_url', 'product_id', 'category_id']),

            'categories'       => CategoryResource::collection($categories),
            'featured'         => ProductResource::collection($this->base()->featured()->latest()->take(12)->get()),
            'new_arrivals'     => ProductResource::collection($this->base()->latest()->take(12)->get()),
            'offers'           => ProductResource::collection($this->offersQuery()->take(12)->get()),
            'influencer_picks' => ProductResource::collection(
                $this->base()
                    ->whereHas('influencers', function ($q) {
                        $q->where('users.is_active', true)->where('influencer_product.is_active', true);
                    })
                    ->latest()
                    ->take(12)
                    ->get()
            ),
        ]);
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
