<?php

declare(strict_types=1);

namespace PeppolSh;

/**
 * The SDK version. Packagist reads the version from the git tag, so this
 * constant is the only place where the code knows its own version.
 */
final class Version
{
    public const VERSION = '0.1.0';

    private function __construct()
    {
    }
}
