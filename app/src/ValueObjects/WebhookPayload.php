<?php

/**
 * Value Object (DDD)
 *
 * Represents the data received in the partner webhook notification.
 * Immutable — reflects an event that already occurred and must not change.
 * Centralizes array conversion for Redis persistence.
 */

namespace App\ValueObjects;

class WebhookPayload
{
    public function __construct(
        public readonly string $sessionKey,
        public readonly string $phone,
        public readonly string $plan,
        public readonly float  $price,
        public readonly string $status,
    ) {}

    public function toArray(): array
    {
        return [
            'phone'  => $this->phone,
            'plan'   => $this->plan,
            'price'  => $this->price,
            'status' => $this->status,
        ];
    }
}