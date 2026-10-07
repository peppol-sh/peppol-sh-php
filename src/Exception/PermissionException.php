<?php

declare(strict_types=1);

namespace PeppolSh\Exception;

/**
 * HTTP 403: authenticated, but not permitted to do this.
 */
class PermissionException extends ApiException
{
}
