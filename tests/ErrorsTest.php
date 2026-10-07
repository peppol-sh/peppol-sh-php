<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Client;
use PeppolSh\Exception\ApiException;
use PeppolSh\Exception\AuthenticationException;
use PeppolSh\Exception\ConflictException;
use PeppolSh\Exception\NotFoundException;
use PeppolSh\Exception\PeppolException;
use PeppolSh\Exception\PermissionException;
use PeppolSh\Exception\RateLimitException;
use PeppolSh\Exception\ServerException;
use PeppolSh\Exception\ValidationException;
use PeppolSh\HttpClient\HttpResponse;
use PeppolSh\Testing\FakeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorsTest extends TestCase
{
    /**
     * @dataProvider statusMap
     *
     * @param class-string<ApiException> $expected
     */
    #[DataProvider('statusMap')]
    public function testStatusMapsToExceptionClass(int $status, string $expected): void
    {
        $http = new FakeHttpClient([FakeHttpClient::error($status, 'some_code', 'Some message', 'invalid_request')]);
        $client = new Client('ps_test_abc', ['http_client' => $http, 'max_retries' => 0]);

        try {
            $client->documents()->send(Payloads::invoice());
            self::fail('Expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame($expected, get_class($e));
            self::assertInstanceOf(PeppolException::class, $e);
            self::assertSame($status, $e->getStatusCode());
            self::assertSame($status, $e->getCode());
            self::assertSame('some_code', $e->getErrorCode());
            self::assertSame('invalid_request', $e->getErrorType());
            self::assertSame('Some message', $e->getMessage());
        }
    }

    /**
     * @return array<int|string, array{int, class-string<ApiException>}>
     */
    public static function statusMap(): array
    {
        return [
            '400' => [400, ValidationException::class],
            '422' => [422, ValidationException::class],
            '401' => [401, AuthenticationException::class],
            '403' => [403, PermissionException::class],
            '404' => [404, NotFoundException::class],
            '409' => [409, ConflictException::class],
            '429' => [429, RateLimitException::class],
            '500' => [500, ServerException::class],
            '503' => [503, ServerException::class],
            '402' => [402, ApiException::class],
            '418' => [418, ApiException::class],
            '302' => [302, ApiException::class],
        ];
    }

    public function testEnvelopeFieldsAndHeadersAreExposed(): void
    {
        $raw = '{"error":{"type":"invalid_request","code":"validation_failed","message":"Bad field",'
            . '"param":"document.lines[0].quantity","details":[{"rule":"BR-CO-10"}]}}';
        $response = new HttpResponse(422, ['Content-Type' => 'application/json', 'X-Request-Id' => 'req_123'], $raw);

        $e = ApiException::fromResponse($response);

        self::assertInstanceOf(ValidationException::class, $e);
        self::assertSame('document.lines[0].quantity', $e->getParam());
        self::assertSame([['rule' => 'BR-CO-10']], $e->getDetails());
        self::assertSame($raw, $e->getRawBody());
        self::assertSame('req_123', $e->getRequestId());
        self::assertSame('req_123', $e->getHeaders()['x-request-id']);
    }

    public function testNonJsonBodyGivesGenericFields(): void
    {
        $e = ApiException::fromResponse(new HttpResponse(502, ['content-type' => 'text/html'], " <h1>Bad Gateway</h1>\n"));

        self::assertInstanceOf(ServerException::class, $e);
        self::assertSame('<h1>Bad Gateway</h1>', $e->getMessage());
        self::assertSame('internal_error', $e->getErrorType());
        self::assertSame('unknown_error', $e->getErrorCode());
        self::assertNull($e->getParam());
        self::assertNull($e->getDetails());
        self::assertNull($e->getRequestId());
    }

    public function testEmptyBodyGivesHttpStatusMessage(): void
    {
        $e = ApiException::fromResponse(new HttpResponse(404));

        self::assertInstanceOf(NotFoundException::class, $e);
        self::assertSame('HTTP 404', $e->getMessage());
    }

    public function testJsonThatIsNotTheEnvelopeFallsBackToTheRawBody(): void
    {
        $bodies = ['{"message":"nope"}', '{"error":"a string"}', '[1,2]', '"text"', '{"error":{"message":""}}'];
        foreach ($bodies as $body) {
            $e = ApiException::fromResponse(new HttpResponse(400, [], $body));
            self::assertSame($body, $e->getMessage());
            self::assertSame('unknown_error', $e->getErrorCode());
        }
    }

    public function testEnvelopeWithWrongFieldTypesDoesNotThrow(): void
    {
        $e = ApiException::fromResponse(new HttpResponse(400, [], '{"error":{"type":1,"code":[],"message":"m","param":5}}'));

        self::assertSame('m', $e->getMessage());
        self::assertSame('internal_error', $e->getErrorType());
        self::assertSame('unknown_error', $e->getErrorCode());
        self::assertNull($e->getParam());
    }

    public function testRateLimitExposesRetryAfter(): void
    {
        $withHeader = ApiException::fromResponse(FakeHttpClient::error(429, 'rate_limited', 'Slow down', 'rate_limit', ['Retry-After' => '7']));
        $without = ApiException::fromResponse(FakeHttpClient::error(429));
        $httpDate = ApiException::fromResponse(
            FakeHttpClient::error(429, 'rate_limited', 'Slow down', 'rate_limit', ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'])
        );

        self::assertInstanceOf(RateLimitException::class, $withHeader);
        self::assertSame(7.0, $withHeader->getRetryAfter());
        self::assertInstanceOf(RateLimitException::class, $without);
        self::assertNull($without->getRetryAfter());
        self::assertInstanceOf(RateLimitException::class, $httpDate);
        self::assertNull($httpDate->getRetryAfter());
    }

    public function testParseRetryAfter(): void
    {
        self::assertNull(ApiException::parseRetryAfter(null));
        self::assertNull(ApiException::parseRetryAfter(''));
        self::assertNull(ApiException::parseRetryAfter('soon'));
        self::assertNull(ApiException::parseRetryAfter('-1'));
        self::assertSame(0.0, ApiException::parseRetryAfter('0'));
        self::assertSame(1.5, ApiException::parseRetryAfter(' 1.5 '));
    }
}
