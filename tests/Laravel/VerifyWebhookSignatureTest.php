<?php

declare(strict_types=1);

namespace PeppolSh\Tests\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use PeppolSh\Webhook;

final class VerifyWebhookSignatureTest extends LaravelTestCase
{
    private const BODY = '{"id":"evt_1","type":"document.delivered","data":{"id":"doc_1"}}';

    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->laravel()->make('router');
        self::assertInstanceOf(Router::class, $router);

        $echo = static function (Request $request): array {
            return ['received' => $request->json('id'), 'raw_length' => strlen((string) $request->getContent())];
        };
        $router->post('/webhooks/peppol', $echo)->middleware('peppol.webhook');
        $router->post('/web/webhooks/peppol', $echo)->middleware(['web', 'peppol.webhook']);
    }

    /**
     * @return array{int, string}
     */
    private function deliver(string $body, ?string $signature, string $uri = '/webhooks/peppol'): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_X_PEPPOL_SIGNATURE_V2'] = $signature;
        }

        $response = $this->call('POST', $uri, [], [], [], $server, $body);

        return [$response->getStatusCode(), (string) $response->getContent()];
    }

    private static function assertGenericError(string $content): void
    {
        self::assertSame(
            ['error' => [
                'type' => 'invalid_request',
                'code' => 'invalid_signature',
                'message' => 'The webhook signature is not valid.',
            ]],
            json_decode($content, true)
        );
    }

    public function testValidSignaturePasses(): void
    {
        [$status, $content] = $this->deliver(self::BODY, Webhook::signatureHeader(self::BODY, self::WEBHOOK_SECRET));

        self::assertSame(200, $status);
        self::assertSame(['received' => 'evt_1', 'raw_length' => strlen(self::BODY)], json_decode($content, true));
    }

    public function testMiddlewareWorksInTheWebGroup(): void
    {
        $header = Webhook::signatureHeader(self::BODY, self::WEBHOOK_SECRET);

        self::assertSame(200, $this->deliver(self::BODY, $header, '/web/webhooks/peppol')[0]);
        self::assertSame(400, $this->deliver(self::BODY . ' ', $header, '/web/webhooks/peppol')[0]);
    }

    public function testTamperedBodyIsRejected(): void
    {
        $header = Webhook::signatureHeader(self::BODY, self::WEBHOOK_SECRET);

        [$status, $content] = $this->deliver(str_replace('doc_1', 'doc_2', self::BODY), $header);

        self::assertSame(400, $status);
        self::assertGenericError($content);
    }

    public function testBodyIsVerifiedByteForByte(): void
    {
        // Same JSON value, different bytes: a re-encoded body must not pass.
        $header = Webhook::signatureHeader(self::BODY, self::WEBHOOK_SECRET);

        self::assertSame(400, $this->deliver(self::BODY . "\n", $header)[0]);
    }

    public function testMissingHeaderIsRejected(): void
    {
        [$status, $content] = $this->deliver(self::BODY, null);

        self::assertSame(400, $status);
        self::assertGenericError($content);
    }

    public function testMalformedHeaderIsRejected(): void
    {
        self::assertSame(400, $this->deliver(self::BODY, 'not-a-signature')[0]);
        self::assertSame(400, $this->deliver(self::BODY, '')[0]);
    }

    public function testWrongSecretIsRejected(): void
    {
        [$status, $content] = $this->deliver(self::BODY, Webhook::signatureHeader(self::BODY, 'whsec_other'));

        self::assertSame(400, $status);
        self::assertGenericError($content);
    }

    public function testStaleTimestampIsRejected(): void
    {
        $header = Webhook::signatureHeader(self::BODY, self::WEBHOOK_SECRET, time() - 301);

        [$status, $content] = $this->deliver(self::BODY, $header);

        self::assertSame(400, $status);
        // Same generic body: the response does not say that the time was the cause.
        self::assertGenericError($content);
    }

    public function testFutureTimestampIsRejected(): void
    {
        $header = Webhook::signatureHeader(self::BODY, self::WEBHOOK_SECRET, time() + 3600);

        self::assertSame(400, $this->deliver(self::BODY, $header)[0]);
    }

    public function testToleranceComesFromTheConfig(): void
    {
        $header = Webhook::signatureHeader(self::BODY, self::WEBHOOK_SECRET, time() - 120);

        self::assertSame(200, $this->deliver(self::BODY, $header)[0]);

        // A string, as a value from .env is.
        $this->configRepository()->set('peppol.webhook_tolerance', '60');
        self::assertSame(400, $this->deliver(self::BODY, $header)[0]);

        // 0 disables the time check.
        $this->configRepository()->set('peppol.webhook_tolerance', 0);
        $old = Webhook::signatureHeader(self::BODY, self::WEBHOOK_SECRET, 1700000000);
        self::assertSame(200, $this->deliver(self::BODY, $old)[0]);
    }

    public function testRotatedHeaderWithTwoSignaturesPasses(): void
    {
        $timestamp = time();
        $old = hash_hmac('sha256', $timestamp . '.' . self::BODY, 'whsec_old');
        $new = hash_hmac('sha256', $timestamp . '.' . self::BODY, self::WEBHOOK_SECRET);

        // During a rotation the header has one v1 for each active secret.
        self::assertSame(200, $this->deliver(self::BODY, 't=' . $timestamp . ',v1=' . $old . ',v1=' . $new)[0]);
        self::assertSame(200, $this->deliver(self::BODY, 't=' . $timestamp . ',v1=' . $new . ',v1=' . $old)[0]);
        self::assertSame(400, $this->deliver(self::BODY, 't=' . $timestamp . ',v1=' . $old . ',v1=' . $old)[0]);
    }

    public function testMissingSecretIsAServerError(): void
    {
        $this->configRepository()->set('peppol.webhook_secret', null);

        [$status, $content] = $this->deliver(self::BODY, Webhook::signatureHeader(self::BODY, self::WEBHOOK_SECRET));

        self::assertSame(500, $status);
        self::assertStringNotContainsString('invalid_signature', $content);
    }

    public function testEmptySecretIsAServerErrorAlsoWithoutAHeader(): void
    {
        $this->configRepository()->set('peppol.webhook_secret', '');

        self::assertSame(500, $this->deliver(self::BODY, null)[0]);
    }
}
