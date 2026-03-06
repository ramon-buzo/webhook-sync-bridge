<?php

/**
 * Adapter Pattern
 *
 * Adapts the native PHP Redis extension API to the CacheClientInterface contract.
 * Isolates the infrastructure detail (Redis) from the business logic.
 * If the underlying technology changes, only this class needs to be updated.
 */

namespace App\Infrastructure;

use App\Contracts\CacheClientInterface;

class RedisClient implements CacheClientInterface
{
    private \Redis $redis;

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect(
            host: getenv('REDIS_HOST') ?: 'redis',
            port: 6379
        );
    }

    public function waitForWebhook(string $sessionKey, int $timeout = 5): ?array
    {
        $result = $this->redis->blPop($sessionKey, $timeout);

        if (empty($result)) {
            return null;
        }

        return json_decode($result[1], true);
    }

    public function publishWebhookResult(string $sessionKey, array $data): void
    {
        $this->redis->lPush($sessionKey, json_encode($data));
        $this->redis->expire($sessionKey, 60);
    }
}