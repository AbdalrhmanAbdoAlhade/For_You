<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PreviewImageService
{
    public static function getBrandPreviewImages(int $brandId, ?string $logo = null): array
    {
        $products = DB::table('products')
            ->where('brand_id', $brandId)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->whereNotNull('images')
            ->orderBy('id')
            ->select('images')
            ->get();

        $images = [];

        foreach ($products as $product) {
            $image = self::extractFirstImage($product->images);

            if ($image !== null) {
                $images[] = $image;
            }

            if (count($images) >= 3) {
                break;
            }
        }

        if (empty($images) && !empty($logo)) {
            $images[] = $logo;
        }

        return $images;
    }

    public static function getInfluencerBrandPreviewImages(
        int $influencerId,
        int $brandId,
        ?string $brandLogo = null
    ): array {
        $products = DB::table('influencer_product as ip')
            ->join('products as p', 'p.id', '=', 'ip.product_id')
            ->where('ip.influencer_id', $influencerId)
            ->where('p.brand_id', $brandId)
            ->where('ip.is_active', 1)
            ->where('p.is_active', 1)
            ->whereNull('p.deleted_at')
            ->whereNotNull('p.images')
            ->orderBy('p.id')
            ->select('p.images')
            ->get();

        $images = [];

        foreach ($products as $product) {
            $image = self::extractFirstImage($product->images);

            if ($image !== null) {
                $images[] = $image;
            }

            if (count($images) >= 3) {
                break;
            }
        }

        if (empty($images) && !empty($brandLogo)) {
            $images[] = $brandLogo;
        }

        return $images;
    }

    private static function extractFirstImage(?string $images): ?string
    {
        if (empty($images)) {
            return null;
        }

        $decoded = json_decode($images, true);

        if (!is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $image) {
            if (is_string($image) && trim($image) !== '') {
                return $image;
            }
        }

        return null;
    }
}
