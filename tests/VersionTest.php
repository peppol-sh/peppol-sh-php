<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testVersionIsSemver(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', Version::VERSION);
    }

    /**
     * composer.json has no version (Packagist reads the git tag), so the
     * changelog is the only file the constant can drift from.
     */
    public function testVersionMatchesTheNewestChangelogRelease(): void
    {
        $changelog = dirname(__DIR__) . '/CHANGELOG.md';
        if (!is_file($changelog)) {
            self::markTestSkipped('No CHANGELOG.md in this checkout.');
        }

        $found = preg_match('/^##\s*\[?v?(\d+\.\d+\.\d+[^\]\s]*)\]?/m', (string) file_get_contents($changelog), $match);
        if ($found !== 1) {
            self::markTestSkipped('CHANGELOG.md has no released version heading.');
        }

        self::assertSame($match[1], Version::VERSION, 'Version::VERSION must match the newest CHANGELOG.md release.');
    }
}
