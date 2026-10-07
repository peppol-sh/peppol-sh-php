<?php

declare(strict_types=1);

namespace PeppolSh\Exception;

/**
 * HTTP 401: the API key is missing, malformed, or unknown.
 */
class AuthenticationException extends ApiException
{
}
