<?php

declare(strict_types=1);

namespace PeppolSh\Tests\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use PeppolSh\Client;
use PeppolSh\Laravel\Facades\Peppol;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * Symfony VarDumper (`dump()` and `dd()` in Laravel) reads the private
 * properties of an object directly. `Client::__debugInfo()` must replace the
 * API key there too.
 */
final class VarDumperTest extends LaravelTestCase
{
    private const SECRET = 'SECRETSECRETSECRET';

    /**
     * @param Application $app
     *
     * @return void
     */
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $config = $app->make('config');
        self::assertInstanceOf(Repository::class, $config);
        $config->set('peppol.api_key', 'ps_test_' . self::SECRET);
    }

    public function testDumpOfTheClientHidesTheApiKey(): void
    {
        $dump = self::dump($this->laravel()->make(Client::class));

        self::assertStringNotContainsString('SECRET', $dump);
        self::assertStringContainsString('-apiKey: "ps_test_..."', $dump);
        self::assertStringContainsString('-baseUrl: "https://sandbox.peppol.sh"', $dump);
    }

    public function testDumpOfAResourceHidesTheApiKey(): void
    {
        $dump = self::dump(Peppol::documents());

        self::assertStringContainsString('Documents', $dump);
        self::assertStringNotContainsString('SECRET', $dump);
        self::assertStringContainsString('-apiKey: "ps_test_..."', $dump);
    }

    public function testClonerThatWasMadeBeforeTheProviderHidesTheApiKey(): void
    {
        // Laravel makes its cloner before a package provider runs, so the
        // protection cannot depend on a caster that the provider registers.
        $dump = self::dump([$this->laravel()->make(Client::class)], new VarCloner([]));

        self::assertStringNotContainsString('SECRET', $dump);
        self::assertStringContainsString('ps_test_...', $dump);
    }

    /**
     * @param mixed $value
     */
    private static function dump($value, ?VarCloner $cloner = null): string
    {
        $cloner = $cloner ?? new VarCloner();
        $dumper = new CliDumper();
        $dumper->setColors(false);

        return (string) $dumper->dump($cloner->cloneVar($value), true);
    }
}
