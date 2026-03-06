<?php

/**
 * Interface Segregation (SOLID) + Dependency Inversion (SOLID)
 *
 * Defines the contract any cache implementation must fulfill.
 * The rest of the system depends on this abstraction, not on RedisClient directly.
 * Allows swapping Redis for Memcached or any other mechanism without touching services.
 */

namespace App\Contracts;

interface CacheClientInterface
{
    public function waitForWebhook(string $sessionKey, int $timeout = 5): ?array;
    public function publishWebhookResult(string $sessionKey, array $data): void;
}