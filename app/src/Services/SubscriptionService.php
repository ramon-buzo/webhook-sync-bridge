<?php

/**
 * Service Layer Pattern
 * Dependency Injection (SOLID)
 *
 * Orchestrates the full subscription flow:
 * calls the partner API, blocks on Redis BLPOP, and returns the result.
 * Dependencies are injected via constructor — this class never instantiates
 * its own dependencies, making it fully testable and loosely coupled.
 */

namespace App\Services;

use App\Contracts\CacheClientInterface;
use App\Contracts\PartnerClientInterface;
use App\ValueObjects\SubscriptionRequest;

class SubscriptionService
{
    public function __construct(
        private readonly PartnerClientInterface $partner,
        private readonly CacheClientInterface   $cache,
    ) {}

    public function subscribe(SubscriptionRequest $request): array
    {
        $sessionKey = 'wsb:' . bin2hex(random_bytes(16));

        $ack = $this->partner->subscribe(
            $sessionKey,
            $request->phone,
            $request->plan,
            $request->forceError,
        );

        if ($ack !== 0) {
            return [
                'success' => false,
                'error'   => 'partner_rejected',
                'ack'     => $ack,
            ];
        }

        $result = $this->cache->waitForWebhook($sessionKey, 5);

        if ($result === null) {
            return [
                'success' => false,
                'error'   => 'timeout',
            ];
        }

        return [
            'success' => true,
            'data'    => $result,
        ];
    }
}