<?php

declare(strict_types=1);

namespace PeppolSh\Tests\Laravel;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use PeppolSh\Client;
use PeppolSh\Exception\PeppolException;
use PeppolSh\Laravel\ClientFactory;
use PeppolSh\Laravel\Facades\Peppol;
use PeppolSh\Laravel\Http\Middleware\VerifyWebhookSignature;
use PeppolSh\Laravel\PeppolServiceProvider;
use PeppolSh\Resources\Documents;
use PeppolSh\Testing\FakeHttpClient;

final class ServiceProviderTest extends LaravelTestCase
{
    private const ENV_KEYS = [
        'PEPPOL_API_KEY',
        'PEPPOL_BASE_URL',
        'PEPPOL_TIMEOUT',
        'PEPPOL_MAX_RETRIES',
        'PEPPOL_WEBHOOK_SECRET',
        'PEPPOL_WEBHOOK_TOLERANCE',
    ];

    protected function tearDown(): void
    {
        foreach (self::ENV_KEYS as $key) {
            self::setEnv($key, null);
        }

        parent::tearDown();
    }

    private static function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            return;
        }
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }

    public function testClientIsASingletonBuiltFromTheConfig(): void
    {
        $app = $this->laravel();

        $client = $app->make(Client::class);

        self::assertInstanceOf(Client::class, $client);
        self::assertSame($client, $app->make(Client::class));
        self::assertSame('https://sandbox.peppol.sh', $client->getBaseUrl());
        self::assertSame(30.0, $client->getTimeout());
        self::assertSame(2, $client->getMaxRetries());
    }

    public function testPeppolAliasResolvesTheSameSingleton(): void
    {
        $app = $this->laravel();

        self::assertTrue($app->bound('peppol'));
        self::assertSame($app->make(Client::class), $app->make('peppol'));
    }

    public function testConfigDefaultsAreMerged(): void
    {
        $config = $this->configRepository();

        // Not set by the test environment: these come from config/peppol.php.
        self::assertSame(30, $config->get('peppol.timeout'));
        self::assertSame(2, $config->get('peppol.max_retries'));
        self::assertSame(300, $config->get('peppol.webhook_tolerance'));
        self::assertSame(
            ['api_key', 'base_url', 'timeout', 'max_retries', 'webhook_secret', 'webhook_tolerance'],
            array_keys((array) require dirname(__DIR__, 2) . '/config/peppol.php')
        );
    }

    public function testConfigDefaultBaseUrlIsProduction(): void
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require dirname(__DIR__, 2) . '/config/peppol.php';

        self::assertSame(Client::PRODUCTION_BASE_URL, $defaults['base_url']);
        self::assertNull($defaults['api_key']);
        self::assertNull($defaults['webhook_secret']);
    }

    public function testConfigReadsTheEnvironment(): void
    {
        self::setEnv('PEPPOL_API_KEY', 'ps_live_from_env');
        self::setEnv('PEPPOL_BASE_URL', 'https://peppol.example.test/');
        self::setEnv('PEPPOL_TIMEOUT', '7.5');
        self::setEnv('PEPPOL_MAX_RETRIES', '4');
        self::setEnv('PEPPOL_WEBHOOK_SECRET', 'whsec_from_env');
        self::setEnv('PEPPOL_WEBHOOK_TOLERANCE', '60');

        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2) . '/config/peppol.php';

        self::assertSame('ps_live_from_env', $config['api_key']);
        self::assertSame('https://peppol.example.test/', $config['base_url']);
        self::assertSame('whsec_from_env', $config['webhook_secret']);
        self::assertSame('60', $config['webhook_tolerance']);

        // The values of .env are strings: the provider converts the numbers.
        $this->configRepository()->set('peppol', $config);
        $client = $this->laravel()->make(Client::class);

        self::assertSame('https://peppol.example.test', $client->getBaseUrl());
        self::assertSame(7.5, $client->getTimeout());
        self::assertSame(4, $client->getMaxRetries());
    }

    public function testMissingApiKeyDoesNotBreakBootAndThrowsOnFirstResolve(): void
    {
        // The application of this test is booted already: no exception so far.
        $this->configRepository()->set('peppol.api_key', null);

        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('Set PEPPOL_API_KEY in your .env file');

        $this->laravel()->make(Client::class);
    }

    public function testEmptyApiKeyThrowsTheSameError(): void
    {
        $this->configRepository()->set('peppol.api_key', '  ');

        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('The peppol.sh API key is not configured');

        Peppol::health();
    }

    public function testApiKeyWithWhitespaceAtTheEndsIsTrimmed(): void
    {
        $this->configRepository()->set('peppol.api_key', " ps_test_abc\n");
        $http = new FakeHttpClient([FakeHttpClient::json(['status' => 'ok'])]);

        ClientFactory::make($this->configRepository()->get('peppol'), ['http_client' => $http])->health();

        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('Bearer ps_test_abc', $request->getHeader('authorization'));
    }

    public function testApiKeyWithWhitespaceInsideNamesTheEnvKey(): void
    {
        $this->configRepository()->set('peppol.api_key', "ps_test_SECRET\nPEPPOL_BASE_URL=x");

        $message = null;
        try {
            $this->laravel()->make(Client::class);
        } catch (PeppolException $e) {
            $message = $e->getMessage();
        }

        self::assertNotNull($message, 'Expected a PeppolException');
        self::assertStringContainsString('Correct PEPPOL_API_KEY in your .env file', $message);
        self::assertStringNotContainsString('SECRET', $message);
    }

    public function testNonFiniteTimeoutFromTheConfigIsRejected(): void
    {
        // A numeric string that is too large converts to INF.
        $this->configRepository()->set('peppol.timeout', '1e999');

        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('"timeout"');

        $this->laravel()->make(Client::class);
    }

    public function testInvalidConfigValueIsReportedByTheClient(): void
    {
        $this->configRepository()->set('peppol.max_retries', 'many');

        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('"max_retries"');

        $this->laravel()->make(Client::class);
    }

    public function testFacadeProxiesToTheContainerClient(): void
    {
        $client = $this->laravel()->make(Client::class);

        self::assertSame($client, Peppol::getFacadeRoot());
        self::assertInstanceOf(Documents::class, Peppol::documents());
        self::assertSame($client->documents(), Peppol::documents());
        self::assertSame('https://sandbox.peppol.sh', Peppol::getBaseUrl());
    }

    public function testFacadeAliasIsRegistered(): void
    {
        self::assertTrue(class_exists('Peppol'));
        self::assertTrue(is_a('Peppol', Peppol::class, true));
    }

    public function testConfigIsPublishableWithTheTag(): void
    {
        $paths = ServiceProvider::pathsToPublish(PeppolServiceProvider::class, 'peppol-config');

        self::assertCount(1, $paths);
        $source = (string) array_keys($paths)[0];
        self::assertFileExists($source);
        self::assertSame(realpath(dirname(__DIR__, 2) . '/config/peppol.php'), realpath($source));
        self::assertSame($this->laravel()->configPath('peppol.php'), array_values($paths)[0]);
    }

    public function testWebhookMiddlewareAliasIsRegistered(): void
    {
        $router = $this->laravel()->make('router');
        self::assertInstanceOf(Router::class, $router);

        $aliases = $router->getMiddleware();

        self::assertArrayHasKey('peppol.webhook', $aliases);
        self::assertSame(VerifyWebhookSignature::class, $aliases['peppol.webhook']);
    }

    public function testComposerExtraDeclaresTheProviderAndTheAlias(): void
    {
        /** @var array{extra: array{laravel: array{providers: list<string>, aliases: array<string, string>}}, require: array<string, string>} $composer */
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame([PeppolServiceProvider::class], $composer['extra']['laravel']['providers']);
        self::assertSame(['Peppol' => Peppol::class], $composer['extra']['laravel']['aliases']);
        // The SDK is a plain PHP package: Laravel is optional.
        foreach (array_keys($composer['require']) as $package) {
            self::assertStringStartsNotWith('illuminate/', $package);
            self::assertStringStartsNotWith('laravel/', $package);
        }
    }
}
