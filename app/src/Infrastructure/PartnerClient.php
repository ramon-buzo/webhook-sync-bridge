<?php

/**
 * Adapter Pattern
 *
 * Adapts the external partner HTTP API to the PartnerClientInterface contract.
 * Encapsulates all cURL communication details, keeping SubscriptionService
 * unaware of how the partner is contacted.
 */

namespace App\Infrastructure;

use App\Contracts\PartnerClientInterface;

class PartnerClient implements PartnerClientInterface
{
    private string $partnerUrl;

    public function __construct()
    {
        $this->partnerUrl = getenv('PARTNER_MOCK_URL') ?: 'http://partner-mock:8082';
    }

    public function subscribe(string $sessionKey, string $phone, string $plan, bool $forceError = false): int
    {
        $payload = json_encode([
            'session_key' => $sessionKey,
            'phone'       => $phone,
            'plan'        => $plan,
            'force_error' => $forceError,
        ]);

        $ch = curl_init("{$this->partnerUrl}/subscribe");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log('Partner response httpCode: ' . $httpCode . ' body: ' . $response);

        if ($httpCode !== 200) {
            return -1;
        }

        $data = json_decode($response, true);
        return (int) ($data['ack'] ?? -1);
    }
}