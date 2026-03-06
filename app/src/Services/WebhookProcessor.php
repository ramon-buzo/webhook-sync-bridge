<?php

/**
 * Service Layer Pattern
 * Dependency Injection (SOLID)
 *
 * Handles incoming webhook notifications from the partner.
 * Validates the HMAC signature embedded in the XML payload,
 * checking both authenticity and timestamp freshness to prevent replay attacks.
 * Publishes the result to Redis to unblock the waiting SubscriptionService.
 */

namespace App\Services;

use App\Contracts\CacheClientInterface;
use App\ValueObjects\WebhookPayload;

class WebhookProcessor
{
    private const MAX_TIMESTAMP_AGE = 300; // 5 minutes

    private string $clientSecret;

    public function __construct(
        private readonly CacheClientInterface $cache,
    ) {
        $this->clientSecret = getenv('PARTNER_CLIENT_SECRET') ?: '';
    }

    public function process(string $rawBody): array
    {
        $xml = simplexml_load_string($rawBody);
        if ($xml === false) {
            return ['success' => false, 'error' => 'invalid_xml'];
        }

        $timestamp  = (int)    $xml->timestamp;
        $sessionKey = (string) $xml->session_key;
        $signature  = (string) $xml->signature;

        if (empty($sessionKey)) {
            return ['success' => false, 'error' => 'missing_session_key'];
        }

        // Validate timestamp freshness — reject requests older than 5 minutes
        if (abs(time() - $timestamp) > self::MAX_TIMESTAMP_AGE) {
            return ['success' => false, 'error' => 'timestamp_expired'];
        }

        // Rebuild the XML without the signature for verification
        // Partner signed: HMAC-SHA256(timestamp + xml_with_placeholder, client_secret)
        $rawBodyForVerification = str_replace(
            "<signature>{$signature}</signature>",
            '<signature>{SIGNATURE}</signature>',
            $rawBody
        );

        $expectedSignature = hash_hmac('sha256', $timestamp . $rawBodyForVerification, $this->clientSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            return ['success' => false, 'error' => 'invalid_signature'];
        }

        $payload = new WebhookPayload(
            sessionKey: $sessionKey,
            phone:      (string) $xml->phone,
            plan:       (string) $xml->plan,
            price:      (float)  $xml->price,
            status:     (string) $xml->status,
        );

        $this->cache->publishWebhookResult($payload->sessionKey, $payload->toArray());

        return ['success' => true];
    }
}