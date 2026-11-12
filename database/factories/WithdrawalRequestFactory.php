<?php

namespace Database\Factories;

use App\Models\InfluencerWallet;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithdrawalRequest>
 */
class WithdrawalRequestFactory extends Factory
{
    protected $model = WithdrawalRequest::class;

    public function definition(): array
    {
        $influencer = User::factory()->influencer();

        return [
            'influencer_id'   => $influencer,
            'wallet_id'       => InfluencerWallet::factory()->state([
                'influencer_id' => $influencer,
                'balance'       => 500,
                'total_earned'  => 500,
            ]),
            'amount'          => 100,
            'status'          => 'pending',
            'payment_method'  => 'vodafone_cash',
            'account_details' => '01000000000',
        ];
    }
}
