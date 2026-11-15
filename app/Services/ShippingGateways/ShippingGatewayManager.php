<?php

namespace App\Services\ShippingGateways;

use App\Contracts\ShippingGatewayInterface;

class ShippingGatewayManager
{
    /** @throws \DomainException */
    public function driver(?string $name = null): ShippingGatewayInterface
    {
        $name = $name ?: (string) config('store.shipping_gateway', 'manual');

        return match ($name) {
            'manual' => app(ManualShippingGateway::class),
            default  => throw new \DomainException('بوابة الشحن غير مدعومة'),
        };
    }
}
