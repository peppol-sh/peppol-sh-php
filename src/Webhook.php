<?php

declare(strict_types=1);

namespace PeppolSh;

use PeppolSh\Exception\PeppolException;
use PeppolSh\Exception\SignatureVerificationException;

/**
 * Verifies the signature of an incoming peppol.sh webhook delivery.
 *
 *     $event = \PeppolSh\Webhook::verify(
 *         file_get_contents('php://input'),
 *         $_SERVER['HTTP_X_PEPPOL_SIGNATURE_V2'] ?? '',
 *         $secret
 *     );
 *
 * The header format is `t=<unix seconds>,v1=<hex>[,v1=<hex>]`, where each hex
 * value is HMAC-SHA256(secret, t + "." + rawBody). During a secret rotation
 * the header has one `v1` for each active secret; one match is sufficient.
 * Always pass the body bytes as received, not a re-encoded copy.
 */
final class Webhook
{
    /** The signature header to verify. */
    public const SIGNATURE_HEADER = 'X-Peppol-Signature-V2';

    /** Legacy signature (body only, no replay protection). Not verified by this class. */
    public const LEGACY_SIGNATURE_HEADER = 'X-Peppol-Signature';

    public const TIMESTAMP_HEADER = 'X-Peppol-Timestamp';
    public const EVENT_HEADER = 'X-Peppol-Event';
    public const DELIVERY_ID_HEADER = 'X-Peppol-Delivery-Id';

    /** Permitted difference between the signed time and the local clock, in seconds. */
    public const DEFAULT_TOLERANCE = 300;

    private function __construct()
    {
    }

    /**
     * Verifies the signature and returns the decoded event.
     *
     * @param string   $rawBody         the request body, byte for byte as received
     * @param string   $signatureHeader the value of the `X-Peppol-Signature-V2` header
     * @param string   $secret          the signing secret of the webhook
     * @param int      $tolerance       permitted clock difference in seconds; 0 disables the time check
     * @param int|null $now             unix time to compare against; null uses the system clock
     *
     * @return array<string, mixed>
     *
     * @throws SignatureVerificationException when the header is malformed, no signature matches, or the timestamp is out of tolerance
     * @throws PeppolException                when the signature is valid but the body is not a JSON object
     */
    public static function verify(
        string $rawBody,
        string $signatureHeader,
        #[\SensitiveParameter]
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null
    ): array {
        self::verifySignature($rawBody, $signatureHeader, $secret, $tolerance, $now);

        /** @var array<string, mixed>|scalar|null $event */
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            throw new PeppolException('The webhook signature is valid, but the body is not a JSON object');
        }

        return $event;
    }

    /**
     * Verifies the signature only. Returns nothing when it is valid.
     *
     * @param int      $tolerance permitted clock difference in seconds; 0 disables the time check
     * @param int|null $now       unix time to compare against; null uses the system clock
     *
     * @throws SignatureVerificationException
     */
    public static function verifySignature(
        string $rawBody,
        string $signatureHeader,
        #[\SensitiveParameter]
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null
    ): void {
        if ($secret === '') {
            throw new SignatureVerificationException('The webhook secret is empty');
        }
        if ($tolerance < 0) {
            throw new SignatureVerificationException('The tolerance must be 0 or more');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if ($timestamp === null || preg_match('/\A\d+\z/', $timestamp) !== 1) {
            throw new SignatureVerificationException(
                'Malformed signature header: no valid "t=<unix seconds>" timestamp'
            );
        }
        if ($signatures === []) {
            throw new SignatureVerificationException('Malformed signature header: no "v1=<hex>" signature');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        $matched = false;
        foreach ($signatures as $signature) {
            // No early exit: each candidate gets a constant-time comparison.
            if (hash_equals($expected, strtolower($signature))) {
                $matched = true;
            }
        }
        if (!$matched) {
            throw new SignatureVerificationException(
                'No signature in the header matches the expected signature for this body and secret'
            );
        }

        if ($tolerance > 0) {
            $age = ($now ?? time()) - (int) $timestamp;
            if ($age > $tolerance) {
                throw new SignatureVerificationException(sprintf(
                    'The signature timestamp is too old (%d s old, tolerance %d s)',
                    $age,
                    $tolerance
                ));
            }
            if ($age < -$tolerance) {
                throw new SignatureVerificationException(sprintf(
                    'The signature timestamp is too far in the future (%d s ahead, tolerance %d s)',
                    -$age,
                    $tolerance
                ));
            }
        }
    }

    /**
     * Builds a valid `X-Peppol-Signature-V2` header value. Use it in your
     * tests to sign a fixture body.
     *
     * @param int|null $timestamp unix seconds; null uses the system clock
     */
    public static function signatureHeader(
        string $rawBody,
        #[\SensitiveParameter]
        string $secret,
        ?int $timestamp = null
    ): string {
        $timestamp = $timestamp ?? time();

        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }
}
