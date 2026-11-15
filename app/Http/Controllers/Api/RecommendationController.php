<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(
        protected RecommendationService $recommendations
    ) {}

    /**
     * GET products/trending: "Trending for you".
     * based_on_likes = true يعني اعرض "Based on your likes"، وإلا اعرض "Trending now".
     */
    public function trending(Request $request)
    {
        $request->validate(['limit' => 'nullable|integer|min:1|max:50']);

        $result = $this->recommendations->trendingFor(
            $request->user('sanctum'),
            (int) $request->query('limit', 12)
        );

        return ProductResource::collection($result['products'])->additional([
            'based_on_likes' => $result['based_on_likes'],
        ]);
    }
}