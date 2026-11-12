<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;

class PaymentGatewayManager
{
    /** @throws \DomainException لو البوابة غير مدعومة */
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: (string) config('payments.default');

        return match ($name) {
            'easykash' => app(EasyKashGateway::class),
            default    => throw new \DomainException('بوابة الدفع غير مدعومة'),
        };
    }

    /** @return array<int, string> */
    public function available(): array
    {
        return array_keys((array) config('payments.gateways', []));
    }
}
