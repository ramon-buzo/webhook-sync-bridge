<?php

/**
 * Interface Segregation (SOLID) + Dependency Inversion (SOLID)
 *
 * Defines the contract any partner client must fulfill.
 * Allows swapping the mock partner for a real one in production
 * without modifying SubscriptionService.
 */

namespace App\Contracts;

interface PartnerClientInterface
{
    public function subscribe(string $sessionKey, string $phone, string $plan, bool $forceError = false): int;
}