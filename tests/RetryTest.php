<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Client;
use PeppolSh\Exception\ConnectionException;
use PeppolSh\Exception\NotFoundException;
use PeppolSh\Exception\RateLimitException;
use PeppolSh\Exception\ServerException;
use PeppolSh\Exception\TimeoutException;
use PeppolSh\Testing\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class RetryTest extends TestCase
{
    /** @var list<float> */
    private $sleeps = [];

    /**
     * @param array<string, mixed> $options
     */
    private function client(FakeHttpClient $http, array $options = []): Client
    {
        $this->sleeps = [];

        return new Client('ps_test_abc', $options + [
            'http_client' => $http,
            'sleep' => function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        ]);
    }

    public function testPostIsRetriedOn429(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::error(429),
            FakeHttpClient::error(429),
            FakeHttpClient::json(['id' => 'doc_1'], 202),
        ]);

        $result = $this->client($http)->documents()->send(Payloads::invoice());

        self::assertSame(['id' => 'doc_1'], $result);
        $http->assertRequestCount(3);
        self::assertCount(2, $this->sleeps);
        // Attempt 0: 0.25 s + jitter of < 0.25 s. Attempt 1: 0.5 s + jitter.
        self::assertGreaterThanOrEqual(0.25, $this->sleeps[0]);
        self::assertLessThanOrEqual(0.5, $this->sleeps[0]);
        self::assertGreaterThanOrEqual(0.5, $this->sleeps[1]);
        self::assertLessThanOrEqual(0.75, $this->sleeps[1]);
        // Each attempt sends the same body.
        self::assertSame($http->getRequests()[0]->getBody(), $http->getRequests()[2]->getBody());
    }

    public function testPostIsNotRetriedOn500(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::error(500), FakeHttpClient::json([])]);

        try {
            $this->client($http)->documents()->send(Payloads::invoice());
            self::fail('Expected a ServerException');
        } catch (ServerException $e) {
            self::assertSame(500, $e->getStatusCode());
        }
        $http->assertRequestCount(1);
        self::assertSame([], $this->sleeps);
    }

    public function testGetIsRetriedOn5xx(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::error(500),
            FakeHttpClient::error(503),
            FakeHttpClient::json(['status' => 'ok']),
        ]);

        self::assertSame(['status' => 'ok'], $this->client($http)->health());
        $http->assertRequestCount(3);
        self::assertCount(2, $this->sleeps);
    }

    public function testGetThrowsAfterTheRetriesAreUsed(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::error(500),
            FakeHttpClient::error(500),
            FakeHttpClient::error(502),
            FakeHttpClient::json([]),
        ]);

        try {
            $this->client($http)->health();
            self::fail('Expected a ServerException');
        } catch (ServerException $e) {
            self::assertSame(502, $e->getStatusCode());
        }
        $http->assertRequestCount(3);
        self::assertCount(2, $this->sleeps);
    }

    public function testGetIsNotRetriedOn4xx(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::error(404), FakeHttpClient::json([])]);

        try {
            $this->client($http)->companies()->get('com_x');
            self::fail('Expected an exception');
        } catch (NotFoundException $e) {
            self::assertCount(1, $http->getRequests());
        }
    }

    public function testNetworkFailureIsRetriedOnGet(): void
    {
        $http = new FakeHttpClient([
            new ConnectionException('Could not reach https://api.peppol.sh: reset'),
            FakeHttpClient::json(['status' => 'ok']),
        ]);

        self::assertSame(['status' => 'ok'], $this->client($http)->health());
        $http->assertRequestCount(2);
        self::assertCount(1, $this->sleeps);
    }

    public function testNetworkFailureOnGetThrowsAfterTheRetriesAreUsed(): void
    {
        $failure = new ConnectionException('down');
        $http = new FakeHttpClient([$failure, $failure, $failure, FakeHttpClient::json([])]);

        try {
            $this->client($http)->health();
            self::fail('Expected a ConnectionException');
        } catch (ConnectionException $e) {
            self::assertSame($failure, $e);
        }
        $http->assertRequestCount(3);
    }

    public function testNetworkFailureIsNotRetriedOnPost(): void
    {
        $http = new FakeHttpClient([new ConnectionException('down'), FakeHttpClient::json([])]);

        try {
            $this->client($http)->documents()->send(Payloads::invoice());
            self::fail('Expected a ConnectionException');
        } catch (ConnectionException $e) {
            $http->assertRequestCount(1);
        }
        self::assertSame([], $this->sleeps);
    }

    public function testRetryAfterIsUsed(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::error(429, 'rate_limited', 'Slow', 'rate_limit', ['Retry-After' => '1']),
            FakeHttpClient::json([]),
        ]);

        $this->client($http)->health();

        self::assertSame([1.0], $this->sleeps);
    }

    public function testRetryAfterIsCappedAtTwoSeconds(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::error(429, 'rate_limited', 'Slow', 'rate_limit', ['Retry-After' => '120']),
            FakeHttpClient::error(503, 'unavailable', 'Down', 'internal_error', ['Retry-After' => '0']),
            FakeHttpClient::json([]),
        ]);

        $this->client($http)->health();

        self::assertSame([2.0, 0.0], $this->sleeps);
    }

    public function testBackoffIsCappedAtTwoSeconds(): void
    {
        $responses = array_fill(0, 6, FakeHttpClient::error(429));
        $responses[] = FakeHttpClient::json([]);
        $http = new FakeHttpClient($responses);

        $this->client($http, ['max_retries' => 6])->health();

        self::assertCount(6, $this->sleeps);
        self::assertSame(2.0, $this->sleeps[3]);
        self::assertSame(2.0, $this->sleeps[5]);
        self::assertLessThanOrEqual(2.0, max($this->sleeps));
    }

    public function testTimeoutIsNotRetried(): void
    {
        $http = new FakeHttpClient([new TimeoutException('timed out', 30.0), FakeHttpClient::json([])]);

        try {
            $this->client($http)->health();
            self::fail('Expected a TimeoutException');
        } catch (TimeoutException $e) {
            self::assertSame(30.0, $e->getTimeout());
        }
        $http->assertRequestCount(1);
        self::assertSame([], $this->sleeps);
    }

    public function testMaxRetriesZeroDisablesRetries(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::error(429), FakeHttpClient::json([])]);

        try {
            $this->client($http, ['max_retries' => 0])->health();
            self::fail('Expected a RateLimitException');
        } catch (RateLimitException $e) {
            $http->assertRequestCount(1);
        }
        self::assertSame([], $this->sleeps);
    }

    public function testDefaultSleepWaits(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::error(429, 'rate_limited', 'Slow', 'rate_limit', ['Retry-After' => '0.01']),
            FakeHttpClient::json(['status' => 'ok']),
        ]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $start = microtime(true);
        $client->health();

        self::assertGreaterThanOrEqual(0.009, microtime(true) - $start);
        $http->assertRequestCount(2);
    }
}
