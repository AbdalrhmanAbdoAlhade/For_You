<?php

namespace App\Http\Resources;

use App\Models\InfluencerStoryProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer  = $request->user('sanctum');
        $isOwner = $viewer && (int) $viewer->id === (int) $this->influencer_id;
        $isStaff = $viewer && in_array($viewer->type, ['admin', 'employee'], true);
        $seeStats = $isOwner || $isStaff;

        // setAttribute/withCount بتتحط في attributes، فبنقراها منها من غير ما نتعرض لـ missing attribute
        $attrs = $this->resource->getAttributes();

        return [
            'id'               => $this->id,
            'image'            => $this->image,
            'caption'          => $this->caption,
            'label'            => $this->label,
            'duration_seconds' => $this->duration_seconds,
            'created_at'       => $this->created_at?->toIso8601String(),
            'expires_at'       => $this->expires_at?->toIso8601String(),

            'is_active'   => $this->when($seeStats, $this->is_active),
            'likes_count' => $this->when(array_key_exists('likers_count', $attrs), fn () => (int) $attrs['likers_count']),
            'views_count' => $this->when($seeStats && array_key_exists('viewers_count', $attrs), fn () => (int) $attrs['viewers_count']),
            'is_seen'     => $this->when(array_key_exists('is_seen', $attrs), fn () => (bool) $attrs['is_seen']),
            'is_liked'    => $this->when(array_key_exists('is_liked', $attrs), fn () => (bool) $attrs['is_liked']),

            'influencer' => $this->whenLoaded('influencer', fn () => [
                'id'     => $this->influencer->id,
                'name'   => $this->influencer->name,
                'avatar' => $this->influencer->avatar,
            ]),

            'products' => $this->whenLoaded('storyProducts', fn () => $this->storyProducts
                ->map(fn (InfluencerStoryProduct $sp) => $this->productPayload($sp, $seeStats))
                ->filter()
                ->values()
                ->all()),
        ];
    }

    /** كارت المنتج تحت القصة: براند + اسم + سعر + صورة + رابط الترويج (زرار Shop) */
    protected function productPayload(InfluencerStoryProduct $sp, bool $seeStats): ?array
    {
        $product = $sp->product;

        if (!$product) {
            return null;
        }

        $link  = $sp->promotionalLink;
        $brand = $product->brand;

        $payload = [
            'product_id'  => $product->id,
            'name'        => $product->localized('name'),
            'brand'       => $brand && $brand->is_active ? $brand->localized('name') : null,
            'image'       => $product->images[0] ?? null,
            'price'       => (float) $product->price,
            'final_price' => (float) $product->final_price,
            'in_stock'    => $product->stock > 0,
            'link'        => $link ? ['code' => $link->code, 'url' => $link->full_url] : null,
        ];

        // إحصائيات الرابط للمؤثر صاحب القصة وللأدمن بس
        if ($seeStats && $link) {
            $payload['clicks']      = $link->clicks;
            $payload['conversions'] = $link->conversions;
        }

        return $payload;
    }
}