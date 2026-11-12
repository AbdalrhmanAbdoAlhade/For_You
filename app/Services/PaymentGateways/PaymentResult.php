<?php

namespace App\Services\PaymentGateways;

final class PaymentResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly bool $successful,
        public readonly string $status,
        public readonly ?string $orderNumber = null,
        public readonly ?int $orderId = null,
        public readonly ?float $amount = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}

    /** @param array<string, mixed> $raw */
    public static function paid(
        ?string $orderNumber,
        ?int $orderId,
        ?float $amount,
        ?string $transactionId,
        array $raw = []
    ): self {
        return new self(true, 'paid', $orderNumber, $orderId, $amount, $transactionId, null, $raw);
    }

    /** @param array<string, mixed> $raw */
    public static function failed(
        ?string $orderNumber,
        ?int $orderId,
        ?float $amount,
        ?string $message,
        array $raw = []
    ): self {
        return new self(false, 'failed', $orderNumber, $orderId, $amount, null, $message, $raw);
    }

    /** @param array<string, mixed> $raw */
    public static function ignored(?string $message, array $raw = []): self
    {
        return new self(false, 'ignored', null, null, null, null, $message, $raw);
    }

    public function isPaid(): bool
    {
        return $this->successful && $this->status === 'paid';
    }

    public function isFailed(): bool
    {
        return !$this->successful && $this->status === 'failed';
    }
}
