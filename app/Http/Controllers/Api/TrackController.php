<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PromotionalLinkService;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    public function __construct(
        protected PromotionalLinkService $linkService
    ) {}

    public function show(Request $request, string $code)
    {
        $link = $this->linkService->trackClick(
            $code,
            sha1($request->ip() . '|' . $request->userAgent())
        );

        if (!$link) {
            return response()->json(['message' => 'الرابط غير صالح أو منتهي'], 404);
        }

        // الفرونت يحفظ الـ ref ويبعته مع الطلب
        return response()->json([
            'ref'        => $link->code,
            'product_id' => $link->product_id,
        ]);
    }
}
