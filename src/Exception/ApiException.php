<?php

declare(strict_types=1);

namespace PeppolSh\Exception;

use PeppolSh\HttpClient\HttpResponse;

/**
 * An error the API returned. Subclasses narrow it by HTTP status.
 *
 * `getMessage()` is the API error message. `getCode()` is the HTTP status
 * (the built-in `Exception::getCode()` is an integer, so the API error code
 * string is available from `getErrorCode()`).
 */
class ApiException extends PeppolException
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $errorType;

    /** @var string */
    private $errorCode;

    /** @var string|null */
    private $param;

    /** @var mixed */
    private $details;

    /** @var string */
    private $rawBody;

    /** @var array<string, string> */
    private $headers;

    /**
     * @param mixed                 $details
     * @param array<string, string> $headers response headers, names in lower case
     */
    final public function __construct(
        string $message,
        int $statusCode,
        string $errorType = 'internal_error',
        string $errorCode = 'unknown_error',
        ?string $param = null,
        $details = null,
        string $rawBody = '',
        array $headers = []
    ) {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->errorType = $errorType;
        $this->errorCode = $errorCode;
        $this->param = $param;
        $this->details = $details;
        $this->rawBody = $rawBody;
        $this->headers = $headers;
    }

    /**
     * Turns a non-2xx response into the applicable exception class. Does not
     * throw: a body that is not the canonical error envelope gives a generic
     * message.
     */
    public static function fromResponse(HttpResponse $response): self
    {
        $status = $response->getStatusCode();
        $rawBody = $response->getBody();

        $envelope = [];
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
            $envelope = $decoded['error'];
        }

        $message = self::stringOrNull($envelope['message'] ?? null);
        if ($message === null || $message === '') {
            $message = trim($rawBody) !== '' ? trim($rawBody) : 'HTTP ' . $status;
        }

        $class = self::classForStatus($status);

        return new $class(
            $message,
            $status,
            self::stringOrNull($envelope['type'] ?? null) ?? 'internal_error',
            self::stringOrNull($envelope['code'] ?? null) ?? 'unknown_error',
            self::stringOrNull($envelope['param'] ?? null),
            $envelope['details'] ?? null,
            $rawBody,
            $response->getHeaders()
        );
    }

    /**
     * Reads a `Retry-After` header value as seconds. Ignores the HTTP-date form.
     */
    public static function parseRetryAfter(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        $seconds = (float) $value;

        return is_finite($seconds) && $seconds >= 0 ? $seconds : null;
    }

    /**
     * The HTTP status of the response.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The `error.type` from the API, for example `invalid_request`.
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * The `error.code` from the API, for example `company_not_found`.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * The request field the error refers to, when the API names one.
     */
    public function getParam(): ?string
    {
        return $this->param;
    }

    /**
     * The `error.details` value from the API (usually an array), or null.
     *
     * @return mixed
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * The response body as received.
     */
    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * The response headers, names in lower case.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * The `X-Request-Id` response header. Give it to support.
     */
    public function getRequestId(): ?string
    {
        return $this->headers['x-request-id'] ?? null;
    }

    /**
     * @return class-string<self>
     */
    private static function classForStatus(int $status): string
    {
        switch ($status) {
            case 400:
            case 422:
                return ValidationException::class;
            case 401:
                return AuthenticationException::class;
            case 403:
                return PermissionException::class;
            case 404:
                return NotFoundException::class;
            case 409:
                return ConflictException::class;
            case 429:
                return RateLimitException::class;
        }

        return $status >= 500 ? ServerException::class : self::class;
    }

    /**
     * @param mixed $value
     */
    private static function stringOrNull($value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
