<?php

/**
 * Value Object (DDD)
 *
 * Encapsulates the input data for a subscription request.
 * Immutable by design (readonly) — cannot be modified after creation.
 * Replaces primitive arrays as parameters, making the code
 * more expressive and type-safe.
 */

namespace App\ValueObjects;

class SubscriptionRequest
{
    public function __construct(
        public readonly string $phone,
        public readonly string $plan,
        public readonly bool $forceError = false,
    ) {}
}