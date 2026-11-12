<?php

namespace Database\Factories;

use App\Models\InfluencerWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InfluencerWallet>
 */
class InfluencerWalletFactory extends Factory
{
    protected $model = InfluencerWallet::class;

    public function definition(): array
    {
        return [
            'influencer_id'   => User::factory()->influencer(),
            'balance'         => 0,
            'total_earned'    => 0,
            'total_withdrawn' => 0,
        ];
    }
}
