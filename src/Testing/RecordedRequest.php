<?php

declare(strict_types=1);

namespace PeppolSh\Testing;

/**
 * One request that `FakeHttpClient` received. Immutable.
 */
final class RecordedRequest
{
    /** @var string */
    private $method;

    /** @var string */
    private $url;

    /** @var array<string, string> */
    private $headers;

    /** @var string|null */
    private $body;

    /** @var float */
    private $timeout;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $method, string $url, array $headers, ?string $body, float $timeout)
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = $value;
        }
        $this->method = $method;
        $this->url = $url;
        $this->headers = $normalized;
        $this->body = $body;
        $this->timeout = $timeout;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * The absolute URL, with the query string.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * The path of the URL, without the query string.
     */
    public function getPath(): string
    {
        $path = parse_url($this->url, PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }

    /**
     * The decoded query string.
     *
     * @return array<int|string, mixed>
     */
    public function getQuery(): array
    {
        $query = parse_url($this->url, PHP_URL_QUERY);
        $result = [];
        if (is_string($query)) {
            parse_str($query, $result);
        }

        return $result;
    }

    /**
     * @return array<string, string> header names in lower case
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * The raw request body, or null when the request had none.
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * The request body decoded as JSON, or null when there is no body.
     *
     * @return mixed
     */
    public function getJson()
    {
        return $this->body === null ? null : json_decode($this->body, true);
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }
}
