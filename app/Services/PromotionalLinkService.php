<?php

namespace App\Services;

use App\Models\PromotionalLink;
use App\Models\User;
use Illuminate\Support\Str;

class PromotionalLinkService
{
    public function generate(User $influencer, ?int $productId = null): PromotionalLink
    {
        $code = $this->generateUniqueCode();

        $baseUrl = config('app.frontend_url', 'https://yourstore.com');

        $fullUrl = $productId
            ? "{$baseUrl}/product/{$productId}?ref={$code}"
            : "{$baseUrl}?ref={$code}";

        return PromotionalLink::create([
            'influencer_id' => $influencer->id,
            'product_id'    => $productId,
            'code'          => $code,
            'full_url'      => $fullUrl,
            'is_active'     => true,
        ]);
    }

    public function trackClick(string $code): ?PromotionalLink
    {
        $link = PromotionalLink::where('code', $code)
            ->where('is_active', true)
            ->first();

        if ($link) {
            $link->increment('clicks');
        }

        return $link;
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (PromotionalLink::where('code', $code)->exists());

        return $code;
    }
}
