<?php

declare(strict_types=1);

namespace PeppolSh\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use PeppolSh\Client;
use PeppolSh\Laravel\Http\Middleware\VerifyWebhookSignature;

/**
 * Registers the peppol.sh SDK in a Laravel application (Laravel 8 to 13).
 * Laravel finds this provider through package auto-discovery.
 *
 * - Config: `config/peppol.php`, merged under the `peppol` key. Publish it
 *   with `php artisan vendor:publish --tag=peppol-config`.
 * - Container: `PeppolSh\Client` is a singleton (also available as
 *   `'peppol'`). It is built from the config on first use, so an
 *   application without an API key still boots.
 * - Router: the `peppol.webhook` middleware alias.
 *
 * The provider and the client keep no static state and the client cannot
 * change after construction, so the singleton is safe with Laravel Octane.
 */
class PeppolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(self::configPath(), 'peppol');

        $this->app->singleton(Client::class, static function ($app): Client {
            /** @var \Illuminate\Contracts\Container\Container $app */
            $config = $app->make('config');

            return ClientFactory::make($config instanceof Repository ? $config->get('peppol') : null);
        });
        $this->app->alias(Client::class, 'peppol');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $target = $this->app->configPath('peppol.php');
            $this->publishes([self::configPath() => $target], 'peppol-config');
        }

        $router = $this->app->make('router');
        if ($router instanceof Router) {
            $router->aliasMiddleware('peppol.webhook', VerifyWebhookSignature::class);
        }
    }

    private static function configPath(): string
    {
        return dirname(__DIR__, 2) . '/config/peppol.php';
    }
}
