<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Exception\PeppolException;
use PeppolSh\Exception\SignatureVerificationException;
use PeppolSh\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_new';
    private const OLD_SECRET = 'whsec_old';
    private const BODY = '{"id":"evt_1"}';
    private const T = 1700000000;

    private static function sign(string $body, string $secret, int $timestamp = self::T): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public function testHeaderConstantsMatchTheApi(): void
    {
        // The names in apps/api/src/webhooks/deliver-http.ts.
        self::assertSame('X-Peppol-Signature-V2', Webhook::SIGNATURE_HEADER);
        self::assertSame('X-Peppol-Signature', Webhook::LEGACY_SIGNATURE_HEADER);
        self::assertSame('X-Peppol-Timestamp', Webhook::TIMESTAMP_HEADER);
        self::assertSame('X-Peppol-Event', Webhook::EVENT_HEADER);
        self::assertSame('X-Peppol-Delivery-Id', Webhook::DELIVERY_ID_HEADER);
        self::assertSame(300, Webhook::DEFAULT_TOLERANCE);
    }

    public function testHmacPrimitiveMatchesTheApiTestVector(): void
    {
        // The vector in apps/api/src/webhooks/__tests__/sign.test.ts.
        self::assertSame(
            'f7bc83f430538424b13298e6aa6fb143ef4d59a14946175997479dbc2d1a3cd8',
            hash_hmac('sha256', 'The quick brown fox jumps over the lazy dog', 'key')
        );
        // The V2 scheme signs "<t>.<body>" with the same primitive.
        $body = 'The quick brown fox jumps over the lazy dog';
        $header = 't=1700000000,v1=' . hash_hmac('sha256', '1700000000.' . $body, 'key');
        Webhook::verifySignature($body, $header, 'key', 0);
        self::assertSame($header, Webhook::signatureHeader($body, 'key', 1700000000));
    }

    public function testValidSignatureReturnsTheEvent(): void
    {
        $header = 't=' . self::T . ',v1=' . self::sign(self::BODY, self::SECRET);

        $event = Webhook::verify(self::BODY, $header, self::SECRET, 300, self::T + 10);

        self::assertSame(['id' => 'evt_1'], $event);
    }

    public function testRotationHeaderWithTwoSignaturesMatchesEachSecret(): void
    {
        $header = 't=' . self::T
            . ',v1=' . self::sign(self::BODY, self::SECRET)
            . ',v1=' . self::sign(self::BODY, self::OLD_SECRET);

        self::assertSame(['id' => 'evt_1'], Webhook::verify(self::BODY, $header, self::SECRET, 300, self::T));
        self::assertSame(['id' => 'evt_1'], Webhook::verify(self::BODY, $header, self::OLD_SECRET, 300, self::T));
    }

    public function testToleratesSpacesUpperCaseHexAndUnknownParts(): void
    {
        $header = 't=' . self::T . ', v0=abc, v1=' . strtoupper(self::sign(self::BODY, self::SECRET)) . ' ';

        Webhook::verifySignature(self::BODY, $header, self::SECRET, 300, self::T);
        $this->addToAssertionCount(1);
    }

    public function testTamperedBodyFails(): void
    {
        $header = 't=' . self::T . ',v1=' . self::sign(self::BODY, self::SECRET);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('No signature in the header matches');
        Webhook::verify('{"id":"evt_2"}', $header, self::SECRET, 300, self::T);
    }

    public function testWrongSecretFails(): void
    {
        $header = 't=' . self::T . ',v1=' . self::sign(self::BODY, self::SECRET);

        $this->expectException(SignatureVerificationException::class);
        Webhook::verify(self::BODY, $header, 'whsec_other', 300, self::T);
    }

    public function testChangedTimestampFails(): void
    {
        // The timestamp is part of the signed string: a replay with a new "t" does not verify.
        $header = 't=' . (self::T + 1) . ',v1=' . self::sign(self::BODY, self::SECRET);

        $this->expectException(SignatureVerificationException::class);
        Webhook::verify(self::BODY, $header, self::SECRET, 300, self::T + 1);
    }

    public function testStaleTimestampFails(): void
    {
        $header = 't=' . self::T . ',v1=' . self::sign(self::BODY, self::SECRET);

        Webhook::verifySignature(self::BODY, $header, self::SECRET, 300, self::T + 300);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('too old (301 s old, tolerance 300 s)');
        Webhook::verify(self::BODY, $header, self::SECRET, 300, self::T + 301);
    }

    public function testFutureTimestampFails(): void
    {
        $header = 't=' . self::T . ',v1=' . self::sign(self::BODY, self::SECRET);

        Webhook::verifySignature(self::BODY, $header, self::SECRET, 300, self::T - 300);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('too far in the future');
        Webhook::verify(self::BODY, $header, self::SECRET, 300, self::T - 301);
    }

    public function testToleranceZeroDisablesTheTimeCheck(): void
    {
        $header = 't=' . self::T . ',v1=' . self::sign(self::BODY, self::SECRET);

        self::assertSame(['id' => 'evt_1'], Webhook::verify(self::BODY, $header, self::SECRET, 0, self::T + 999999999));
    }

    public function testDefaultClockIsTheSystemClock(): void
    {
        $fresh = Webhook::signatureHeader(self::BODY, self::SECRET);
        self::assertSame(['id' => 'evt_1'], Webhook::verify(self::BODY, $fresh, self::SECRET));

        $old = 't=' . self::T . ',v1=' . self::sign(self::BODY, self::SECRET);
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('too old');
        Webhook::verify(self::BODY, $old, self::SECRET);
    }

    /**
     * @dataProvider malformedHeaders
     */
    #[DataProvider('malformedHeaders')]
    public function testMalformedHeaderFails(string $header): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Malformed signature header');
        Webhook::verify(self::BODY, $header, self::SECRET, 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedHeaders(): array
    {
        $sig = self::sign(self::BODY, self::SECRET);

        return [
            'empty' => [''],
            'garbage' => ['not a header'],
            'no timestamp' => ['v1=' . $sig],
            'no signature' => ['t=' . self::T],
            'timestamp not a number' => ['t=abc,v1=' . $sig],
            'timestamp negative' => ['t=-5,v1=' . $sig],
            'timestamp empty' => ['t=,v1=' . $sig],
            'legacy bare hex' => [$sig],
            'only unknown scheme' => ['t=' . self::T . ',v2=' . $sig],
        ];
    }

    public function testEmptySignatureValueDoesNotMatch(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('No signature in the header matches');
        Webhook::verify(self::BODY, 't=' . self::T . ',v1=', self::SECRET, 0);
    }

    public function testEmptySecretAndNegativeToleranceAreRejected(): void
    {
        $header = 't=' . self::T . ',v1=' . self::sign(self::BODY, '');

        try {
            Webhook::verify(self::BODY, $header, '', 0);
            self::fail('Expected an exception for the empty secret');
        } catch (SignatureVerificationException $e) {
            self::assertStringContainsString('secret is empty', $e->getMessage());
        }

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('tolerance');
        Webhook::verify(self::BODY, $header, self::SECRET, -1);
    }

    public function testValidSignatureOnNonJsonBodyThrowsBaseException(): void
    {
        $body = 'plain text';
        $header = Webhook::signatureHeader($body, self::SECRET, self::T);

        try {
            Webhook::verify($body, $header, self::SECRET, 0);
            self::fail('Expected a PeppolException');
        } catch (PeppolException $e) {
            self::assertNotInstanceOf(SignatureVerificationException::class, $e);
            self::assertStringContainsString('not a JSON object', $e->getMessage());
        }
    }
}
