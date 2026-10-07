<?php

declare(strict_types=1);

namespace PeppolSh\Exception;

/**
 * HTTP 429: too many requests.
 */
class RateLimitException extends ApiException
{
    /**
     * Seconds to wait, from the `Retry-After` header, or null when the API
     * sent none (or sent the HTTP-date form, which the SDK ignores).
     */
    public function getRetryAfter(): ?float
    {
        return self::parseRetryAfter($this->getHeaders()['retry-after'] ?? null);
    }
}
