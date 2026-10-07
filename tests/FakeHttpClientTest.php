<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\HttpClient\HttpResponse;
use PeppolSh\Testing\FakeHttpClient;
use PeppolSh\Testing\RecordedRequest;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class FakeHttpClientTest extends TestCase
{
    public function testRepliesInOrderAndRecordsRequests(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json(['n' => 1])]);
        $http->push(new HttpResponse(204))->push(static function (RecordedRequest $request): HttpResponse {
            return FakeHttpClient::json(['echo' => $request->getJson()], 201);
        });

        $first = $http->request('GET', 'https://x.test/a?b=1&c[]=2', ['Accept' => 'application/json'], null, 1.0);
        $second = $http->request('DELETE', 'https://x.test/a', [], null, 1.0);
        $third = $http->request('POST', 'https://x.test/a', [], '{"k":"v"}', 2.5);

        self::assertSame('{"n":1}', $first->getBody());
        self::assertSame('application/json', $first->getHeader('Content-Type'));
        self::assertSame(204, $second->getStatusCode());
        self::assertSame(201, $third->getStatusCode());
        self::assertSame('{"echo":{"k":"v"}}', $third->getBody());

        $requests = $http->getRequests();
        self::assertCount(3, $requests);
        self::assertSame('/a', $requests[0]->getPath());
        self::assertSame(['b' => '1', 'c' => ['2']], $requests[0]->getQuery());
        self::assertSame('application/json', $requests[0]->getHeader('ACCEPT'));
        self::assertSame(['accept' => 'application/json'], $requests[0]->getHeaders());
        self::assertNull($requests[0]->getJson());
        self::assertSame($requests[2], $http->getLastRequest());
        self::assertSame(2.5, $requests[2]->getTimeout());

        $http->assertRequestCount(3);
        $http->assertSent('post', '/a');
    }

    public function testThrowsAQueuedThrowable(): void
    {
        $failure = new \RuntimeException('boom');
        $http = new FakeHttpClient([$failure]);

        try {
            $http->request('GET', 'https://x.test/', [], null, 1.0);
            self::fail('Expected the queued exception');
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e);
        }
        $http->assertRequestCount(1);
    }

    public function testEmptyQueueThrows(): void
    {
        $http = new FakeHttpClient();
        self::assertNull($http->getLastRequest());
        $http->assertNothingSent();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no response queued for GET https://x.test/');
        $http->request('GET', 'https://x.test/', [], null, 1.0);
    }

    public function testAssertionsFailAsPhpunitFailures(): void
    {
        $http = new FakeHttpClient([new HttpResponse(200)]);
        $http->request('GET', 'https://x.test/a', [], null, 1.0);

        try {
            $http->assertRequestCount(2);
            self::fail('assertRequestCount did not fail');
        } catch (AssertionFailedError $e) {
            self::assertSame('Expected 2 request(s), got 1', $e->getMessage());
        }

        try {
            $http->assertSent('POST', '/a');
            self::fail('assertSent did not fail');
        } catch (AssertionFailedError $e) {
            self::assertSame('Expected a POST /a request. Requests sent: GET /a', $e->getMessage());
        }
    }

    public function testErrorHelperBuildsTheEnvelope(): void
    {
        $response = FakeHttpClient::error(404, 'not_found', 'No such company', 'not_found', ['X-Request-Id' => 'req_1']);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('req_1', $response->getHeader('x-request-id'));
        self::assertSame(
            ['error' => ['type' => 'not_found', 'code' => 'not_found', 'message' => 'No such company']],
            json_decode($response->getBody(), true)
        );
    }
}
