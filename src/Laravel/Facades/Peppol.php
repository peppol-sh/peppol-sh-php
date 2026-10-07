<?php

declare(strict_types=1);

namespace PeppolSh\Laravel\Facades;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Facade;
use PeppolSh\Client;
use PeppolSh\Laravel\ClientFactory;
use PeppolSh\Testing\FakeHttpClient;

/**
 * Facade for the `PeppolSh\Client` singleton.
 *
 *     Peppol::documents()->send([...]);
 *
 * In a test, `Peppol::fake()` replaces the client with one that sends
 * nothing:
 *
 *     $http = Peppol::fake([FakeHttpClient::json(['id' => 'doc_1', 'status' => 'queued'], 202)]);
 *     // ... run the code under test ...
 *     $http->assertSent('POST', '/v1/documents');
 *
 * @method static \PeppolSh\Resources\Account account()
 * @method static \PeppolSh\Resources\Companies companies()
 * @method static \PeppolSh\Resources\Documents documents()
 * @method static \PeppolSh\Resources\Events events()
 * @method static \PeppolSh\Resources\Kyc kyc()
 * @method static \PeppolSh\Resources\Lookup lookup()
 * @method static \PeppolSh\Resources\Validate validate()
 * @method static \PeppolSh\Resources\Webhooks webhooks()
 * @method static \PeppolSh\Resources\Workspaces workspaces()
 * @method static array<string, mixed> signup(array<string, mixed> $params)
 * @method static array<string, mixed> health()
 * @method static array<string, mixed>|string|null request(string $method, string $path, array<string, mixed> $query = [], ?array<string, mixed> $body = null, bool $auth = true, bool $list = false)
 * @method static \PeppolSh\HttpClient\HttpResponse requestRaw(string $method, string $path, array<string, mixed> $query = [], ?array<string, mixed> $body = null, bool $auth = true, bool $list = false)
 * @method static string getBaseUrl()
 * @method static float getTimeout()
 * @method static int getMaxRetries()
 *
 * @see \PeppolSh\Client
 */
class Peppol extends Facade
{
    /**
     * Replaces the container's `PeppolSh\Client` with one that uses a
     * `FakeHttpClient`, and returns that fake. After this call the facade,
     * the `'peppol'` alias, and each newly injected `PeppolSh\Client` use the
     * fake. An object that got the real client injected before this call
     * keeps the real client, so call `fake()` at the start of the test.
     *
     * The fake client keeps the configured base URL and timeout, does not
     * retry (`max_retries` 0), and does not sleep. No API key is necessary,
     * and the configured API key is not used: each recorded request has
     * `Authorization: Bearer ps_test_fake`, so a real key cannot get into
     * the output of a failed test.
     *
     * @param array<array-key, \PeppolSh\HttpClient\HttpResponse|\Throwable|callable(\PeppolSh\Testing\RecordedRequest): \PeppolSh\HttpClient\HttpResponse> $responses
     *                                                                                                                                                         the queue of replies, used in order; see `FakeHttpClient`
     *
     * @throws \PeppolSh\Exception\PeppolException when a config value is invalid
     */
    public static function fake(array $responses = []): FakeHttpClient
    {
        $app = static::getFacadeApplication();
        if ($app === null) {
            throw new \RuntimeException('Peppol::fake() needs a Laravel application: the facade has none.');
        }

        $config = $app->make('config');
        $peppol = $config instanceof Repository ? $config->get('peppol') : null;
        if (!is_array($peppol)) {
            $peppol = [];
        }
        $peppol['api_key'] = ClientFactory::FAKE_API_KEY;

        $http = new FakeHttpClient($responses);
        $client = ClientFactory::make($peppol, [
            'http_client' => $http,
            'max_retries' => 0,
            'sleep' => static function (float $seconds): void {
            },
        ]);

        // Puts the instance in the container under the accessor
        // (`PeppolSh\Client`), so the `'peppol'` alias gets it too.
        static::swap($client);

        return $http;
    }

    /**
     * The class name, not the `'peppol'` alias: `swap()` binds an instance
     * to the accessor, and an instance bound to an alias name would detach
     * the alias from `PeppolSh\Client`.
     */
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
