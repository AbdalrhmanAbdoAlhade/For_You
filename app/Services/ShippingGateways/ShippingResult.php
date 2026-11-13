<?php

namespace App\Services\ShippingGateways;

final class ShippingResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $orderNumber = null,
        public readonly ?string $status = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}

    /** @param array<string, mixed> $raw */
    public static function ok(
        ?string $trackingNumber,
        ?string $orderNumber,
        ?string $status,
        array $raw = []
    ): self {
        return new self(true, $trackingNumber, $orderNumber, $status, null, $raw);
    }

    /** @param array<string, mixed> $raw */
    public static function invalid(?string $message, array $raw = []): self
    {
        return new self(false, null, null, null, $message, $raw);
    }
}
