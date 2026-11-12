<?php

namespace Database\Factories;

use App\Models\PromotionalLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromotionalLink>
 */
class PromotionalLinkFactory extends Factory
{
    protected $model = PromotionalLink::class;

    public function definition(): array
    {
        $code = Str::upper(Str::random(8));

        return [
            'influencer_id' => User::factory()->influencer(),
            'product_id'    => null,
            'code'          => $code,
            'full_url'      => 'https://example.com?ref=' . $code,
            'clicks'        => 0,
            'conversions'   => 0,
            'is_active'     => true,
            'expires_at'    => null,
        ];
    }
}
