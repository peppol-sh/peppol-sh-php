<?php

declare(strict_types=1);

namespace PeppolSh\HttpClient;

/**
 * An HTTP response as the transport saw it. Immutable.
 */
final class HttpResponse
{
    /** @var int */
    private $statusCode;

    /** @var array<string, string> */
    private $headers;

    /** @var string */
    private $body;

    /**
     * @param array<string, string> $headers header names in any case; they are stored in lower case
     */
    public function __construct(int $statusCode, array $headers = [], string $body = '')
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = $value;
        }
        $this->statusCode = $statusCode;
        $this->headers = $normalized;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string> header names in lower case
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * One header value (the name is not case-sensitive), or null.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
