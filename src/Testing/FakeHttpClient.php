<?php

declare(strict_types=1);

namespace PeppolSh\Testing;

use PeppolSh\HttpClient\HttpClientInterface;
use PeppolSh\HttpClient\HttpResponse;
use PHPUnit\Framework\AssertionFailedError;

/**
 * A transport for tests: it sends nothing, replies from a queue, and records
 * every request.
 *
 *     $http = new FakeHttpClient([FakeHttpClient::json(['id' => 'doc_1'], 202)]);
 *     $peppol = new \PeppolSh\Client('ps_test_x', ['http_client' => $http]);
 *     $peppol->documents()->send([...]);
 *     $http->assertSent('POST', '/v1/documents');
 *
 * A queue entry is an `HttpResponse`, a `Throwable` (it is thrown, for
 * example a `ConnectionException`), or a callable that receives the
 * `RecordedRequest` and returns an `HttpResponse`. Entries are used in order.
 * A request with an empty queue throws a `LogicException`.
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var list<HttpResponse|\Throwable|callable(RecordedRequest): HttpResponse> */
    private $queue = [];

    /** @var list<RecordedRequest> */
    private $requests = [];

    /**
     * @param iterable<HttpResponse|\Throwable|callable(RecordedRequest): HttpResponse> $responses
     */
    public function __construct(iterable $responses = [])
    {
        foreach ($responses as $response) {
            $this->push($response);
        }
    }

    /**
     * Builds a JSON response.
     *
     * @param mixed                 $data
     * @param array<string, string> $headers
     */
    public static function json($data, int $status = 200, array $headers = []): HttpResponse
    {
        return new HttpResponse(
            $status,
            array_merge(['content-type' => 'application/json'], array_change_key_case($headers, CASE_LOWER)),
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Builds the canonical API error response.
     *
     * @param array<string, string> $headers
     */
    public static function error(
        int $status,
        string $code = 'error',
        string $message = 'Error',
        string $type = 'invalid_request',
        array $headers = []
    ): HttpResponse {
        return self::json(['error' => ['type' => $type, 'code' => $code, 'message' => $message]], $status, $headers);
    }

    /**
     * Adds one entry to the end of the queue.
     *
     * @param HttpResponse|\Throwable|callable(RecordedRequest): HttpResponse $response
     *
     * @return $this
     */
    public function push($response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    public function request(string $method, string $url, array $headers, ?string $body, float $timeout): HttpResponse
    {
        $request = new RecordedRequest($method, $url, $headers, $body, $timeout);
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new \LogicException(sprintf('FakeHttpClient has no response queued for %s %s', $method, $url));
        }
        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }
        if ($next instanceof HttpResponse) {
            return $next;
        }

        return $next($request);
    }

    /**
     * @return list<RecordedRequest>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * The most recent request, or null when there was none.
     */
    public function getLastRequest(): ?RecordedRequest
    {
        return $this->requests === [] ? null : $this->requests[count($this->requests) - 1];
    }

    /**
     * Fails when the number of requests is not `$expected`.
     */
    public function assertRequestCount(int $expected): void
    {
        $actual = count($this->requests);
        if ($actual !== $expected) {
            self::fail(sprintf('Expected %d request(s), got %d', $expected, $actual));
        }
    }

    /**
     * Fails when no request with this method and path was sent.
     */
    public function assertSent(string $method, string $path): void
    {
        $seen = [];
        foreach ($this->requests as $request) {
            if ($request->getMethod() === strtoupper($method) && $request->getPath() === $path) {
                return;
            }
            $seen[] = $request->getMethod() . ' ' . $request->getPath();
        }
        self::fail(sprintf(
            'Expected a %s %s request. Requests sent: %s',
            strtoupper($method),
            $path,
            $seen === [] ? '(none)' : implode(', ', $seen)
        ));
    }

    /**
     * Fails when a request was sent.
     */
    public function assertNothingSent(): void
    {
        $this->assertRequestCount(0);
    }

    /**
     * Reports a failure as a PHPUnit failure when PHPUnit is loaded, and as
     * a plain exception in other test runners.
     *
     * @return never
     */
    private static function fail(string $message): void
    {
        if (class_exists(AssertionFailedError::class)) {
            throw new AssertionFailedError($message);
        }

        throw new \RuntimeException($message);
    }
}
