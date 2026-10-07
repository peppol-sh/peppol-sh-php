<?php

declare(strict_types=1);

namespace PeppolSh\Tests\Laravel;

use PeppolSh\Client;
use PeppolSh\Exception\ServerException;
use PeppolSh\Exception\ValidationException;
use PeppolSh\Laravel\Facades\Peppol;
use PeppolSh\Testing\FakeHttpClient;

final class FakeTest extends LaravelTestCase
{
    public function testFakeRecordsTheRequestAndReturnsTheResponse(): void
    {
        $http = Peppol::fake([
            FakeHttpClient::json(['id' => 'doc_1', 'status' => 'queued', 'url' => '/v1/documents/doc_1'], 202),
        ]);

        $result = Peppol::documents()->send(self::invoice());

        self::assertSame(['id' => 'doc_1', 'status' => 'queued', 'url' => '/v1/documents/doc_1'], $result);
        $http->assertRequestCount(1);
        $http->assertSent('POST', '/v1/documents');
        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('https://sandbox.peppol.sh/v1/documents', $request->getUrl());
        self::assertSame(json_decode((string) json_encode(self::invoice()), true), $request->getJson());
    }

    public function testFakeNeverUsesTheConfiguredApiKey(): void
    {
        $this->configRepository()->set('peppol.api_key', 'ps_live_SECRETSECRET');

        $http = Peppol::fake([FakeHttpClient::json(['status' => 'ok'])]);
        Peppol::health();

        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('Bearer ps_test_fake', $request->getHeader('authorization'));
        self::assertStringNotContainsString('SECRET', print_r($http->getRequests(), true));
    }

    public function testInjectedClientAndAliasUseTheFake(): void
    {
        $app = $this->laravel();
        $real = $app->make(Client::class);

        $http = Peppol::fake([FakeHttpClient::json(['status' => 'ok'])]);

        $injected = $app->make(Client::class);
        self::assertInstanceOf(Client::class, $injected);
        self::assertNotSame($real, $injected);
        self::assertSame($injected, Peppol::getFacadeRoot());
        self::assertSame($injected, $app->make('peppol'));
        self::assertSame($injected, $app->make(ClientConsumer::class)->client);

        self::assertSame(['status' => 'ok'], $injected->health());
        $http->assertSent('GET', '/v1/health');
    }

    public function testFakeNeedsNoApiKey(): void
    {
        $this->configRepository()->set('peppol.api_key', null);

        $http = Peppol::fake([FakeHttpClient::json(['status' => 'ok'])]);
        Peppol::health();

        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('Bearer ps_test_fake', $request->getHeader('authorization'));
    }

    public function testFakeDoesNotRetryAndKeepsTheConfig(): void
    {
        $this->configRepository()->set('peppol.timeout', '12');
        $this->configRepository()->set('peppol.max_retries', 5);

        $http = Peppol::fake([FakeHttpClient::error(503, 'unavailable', 'Try again')]);

        self::assertSame(0, Peppol::getMaxRetries());
        self::assertSame(12.0, Peppol::getTimeout());
        self::assertSame('https://sandbox.peppol.sh', Peppol::getBaseUrl());

        $client = $this->laravel()->make(Client::class);
        $status = null;
        try {
            $client->health();
        } catch (ServerException $e) {
            $status = $e->getStatusCode();
        }
        self::assertSame(503, $status);
        // A GET that gets HTTP 503 is retried by a real client; the fake client sends it one time.
        $http->assertRequestCount(1);
    }

    public function testFakeWithNoResponsesSendsNothing(): void
    {
        $http = Peppol::fake();

        $http->assertNothingSent();
        self::assertSame([], $http->getRequests());
    }

    public function testResponsesCanBePushedLaterAndErrorsAreMapped(): void
    {
        $http = Peppol::fake();
        $http->push(FakeHttpClient::error(422, 'validation_failed', 'The document is not valid'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The document is not valid');

        Peppol::documents()->send(self::invoice());
    }

    public function testSecondFakeReplacesTheFirst(): void
    {
        $first = Peppol::fake([FakeHttpClient::json(['n' => 1])]);
        $second = Peppol::fake([FakeHttpClient::json(['n' => 2])]);

        self::assertSame(['n' => 2], Peppol::health());
        $first->assertNothingSent();
        $second->assertRequestCount(1);
    }
}
