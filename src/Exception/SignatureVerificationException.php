<?php

declare(strict_types=1);

namespace PeppolSh\Exception;

/**
 * A webhook signature header is malformed, does not match, or is too old.
 */
class SignatureVerificationException extends PeppolException
{
}
