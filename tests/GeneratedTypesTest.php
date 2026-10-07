<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Generated\Types;
use PHPUnit\Framework\TestCase;

/**
 * Guards `src/Generated/Types.php`, the PHPStan array shapes that
 * `scripts/generate-types.ts` writes from the OpenAPI spec.
 *
 * The comparison with the spec runs only in the monorepo, where the spec, the
 * generator, and Bun are available. It is skipped in the mirrored public
 * repository and in a Composer install. Set the environment variable
 * `PEPPOL_SDK_REQUIRE_TYPES_CHECK=1` to make a skip a failure (the monorepo
 * CI does this).
 */
final class GeneratedTypesTest extends TestCase
{
    private const RUN_HINT = 'run: bun packages/sdk-php/scripts/generate-types.ts';

    public function testTypesFileIsValidPhp(): void
    {
        $file = self::typesFile();
        self::assertFileExists($file);

        // TOKEN_PARSE makes token_get_all() throw a ParseError for a syntax error.
        self::assertNotEmpty(token_get_all((string) file_get_contents($file), TOKEN_PARSE));

        self::assertTrue(class_exists(Types::class));
        $class = new \ReflectionClass(Types::class);
        self::assertTrue($class->isFinal());
        self::assertFalse($class->isInstantiable());
        self::assertNotEmpty(self::declaredAliases());
    }

    public function testEachImportedAliasIsDeclared(): void
    {
        $declared = array_flip(self::declaredAliases());
        $imports = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__) . '/src', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all(
                '/@phpstan-import-type\s+(\w+)\s+from\s+\\\\?PeppolSh\\\\Generated\\\\Types\b/',
                (string) file_get_contents($file->getPathname()),
                $matches
            );
            foreach ($matches[1] as $alias) {
                $imports++;
                self::assertArrayHasKey(
                    $alias,
                    $declared,
                    sprintf(
                        '%s imports the type "%s", which is not in src/Generated/Types.php. '
                        . 'The spec changed or the name is wrong; %s',
                        $file->getPathname(),
                        $alias,
                        self::RUN_HINT
                    )
                );
            }
        }

        self::assertGreaterThan(0, $imports, 'No @phpstan-import-type found in src/: the scan is broken.');
    }

    public function testTypesFileMatchesTheSpec(): void
    {
        $root = dirname(__DIR__, 3);
        $script = dirname(__DIR__) . '/scripts/generate-types.ts';

        if (!is_file($root . '/apps/api/src/openapi.yaml') || !is_file($script)) {
            self::skipDriftCheck('No OpenAPI spec or generator next to the package (mirrored repository).');
        }

        exec('bun --version 2>&1', $versionOutput, $versionStatus);
        if ($versionStatus !== 0) {
            self::skipDriftCheck('Bun is not installed.');
        }

        exec('bun ' . escapeshellarg($script) . ' --check 2>&1', $output, $status);
        $text = implode("\n", $output);

        if ($status !== 0 && $status !== 1) {
            self::skipDriftCheck('The generator could not run: ' . $text);
        }

        self::assertSame(
            0,
            $status,
            "src/Generated/Types.php does not match apps/api/src/openapi.yaml.\n" . self::RUN_HINT . "\n\n" . $text
        );
    }

    private static function typesFile(): string
    {
        return dirname(__DIR__) . '/src/Generated/Types.php';
    }

    /**
     * @return list<string>
     */
    private static function declaredAliases(): array
    {
        preg_match_all('/^ \* @phpstan-type (\w+) /m', (string) file_get_contents(self::typesFile()), $matches);

        return $matches[1];
    }

    /**
     * @return never
     */
    private static function skipDriftCheck(string $reason): void
    {
        if (getenv('PEPPOL_SDK_REQUIRE_TYPES_CHECK') === '1') {
            self::fail('PEPPOL_SDK_REQUIRE_TYPES_CHECK=1, but the comparison with the spec cannot run. ' . $reason);
        }

        self::markTestSkipped($reason);
    }
}
