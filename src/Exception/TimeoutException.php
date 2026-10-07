<?php

declare(strict_types=1);

namespace PeppolSh\Exception;

/**
 * The client stopped waiting before the API gave an answer. Not retried.
 */
class TimeoutException extends PeppolException
{
    /** @var float */
    private $timeout;

    public function __construct(string $message, float $timeout, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->timeout = $timeout;
    }

    /**
     * The timeout that elapsed, in seconds.
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }
}
