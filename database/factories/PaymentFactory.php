<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id'       => Order::factory(),
            'payment_method' => 'manual',
            'transaction_id' => null,
            'amount'         => 100,
            'status'         => 'pending',
        ];
    }

    public function paid(string $transactionId = 'TXN-TEST-001'): static
    {
        return $this->state(fn () => [
            'status'         => 'paid',
            'transaction_id' => $transactionId,
            'paid_at'        => now(),
        ]);
    }
}
