<?php

declare(strict_types=1);

namespace PeppolSh\HttpClient;

use PeppolSh\Exception\ConnectionException;
use PeppolSh\Exception\TimeoutException;

/**
 * The transport the client sends each attempt through. An implementation does
 * one HTTP exchange: it does not retry, does not follow redirects, and does
 * not throw on a 4xx/5xx status. The client does the retries.
 */
interface HttpClientInterface
{
    /**
     * @param string                $method  `GET`, `POST`, `PATCH`, or `DELETE`
     * @param string                $url     absolute URL with the query string
     * @param array<string, string> $headers header name => value
     * @param string|null           $body    request body, or null for no body
     * @param float                 $timeout total time limit in seconds
     *
     * @throws TimeoutException    when the time limit elapsed
     * @throws ConnectionException when the request did not get to the server
     */
    public function request(string $method, string $url, array $headers, ?string $body, float $timeout): HttpResponse;
}
