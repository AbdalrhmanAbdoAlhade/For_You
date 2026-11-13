<?php

namespace App\Services;

use App\Models\PromotionalLink;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class PromotionalLinkService
{
    public function generate(User $influencer, ?int $productId = null): PromotionalLink
    {
        $baseUrl = config('app.frontend_url', 'https://yourstore.com');

        // إعادة المحاولة عند تصادم الكود مع طلب متزامن (unique constraint).
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $code = $this->generateUniqueCode();

            $fullUrl = $productId
                ? "{$baseUrl}/product/{$productId}?ref={$code}"
                : "{$baseUrl}?ref={$code}";

            try {
                return PromotionalLink::create([
                    'influencer_id' => $influencer->id,
                    'product_id'    => $productId,
                    'code'          => $code,
                    'full_url'      => $fullUrl,
                    'is_active'     => true,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === 3) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('تعذر إنشاء كود رابط ترويجي فريد.');
    }

      /**
     * رابط شغال وغير منتهي (من غير ما يزود الـ clicks).
     */
    public function findActive(string $code): ?PromotionalLink
    {
        return PromotionalLink::where('code', $code)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * بيتنادى لما العميل يفتح الرابط. نفس الزائر مايتحسبش أكتر من مرة في الساعة.
     */
    public function trackClick(string $code, ?string $fingerprint = null): ?PromotionalLink
    {
        $link = $this->findActive($code);

        if ($link) {
            $ttl = max(1, (int) config('store.link_click_ttl_minutes', 60));

            $isNewVisit = !$fingerprint
                || Cache::add(
                    "link_click:{$link->id}:{$fingerprint}",
                    1,
                    now()->addMinutes($ttl)
                );

            if ($isNewVisit) {
                $link->increment('clicks');
            }
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
