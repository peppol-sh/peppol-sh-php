<?php

declare(strict_types=1);

namespace PeppolSh\Tests\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Orchestra\Testbench\TestCase;
use PeppolSh\Laravel\Facades\Peppol;
use PeppolSh\Laravel\PeppolServiceProvider;

/**
 * Boots a Laravel application (the version orchestra/testbench selects) with
 * the package provider and a sandbox configuration.
 */
abstract class LaravelTestCase extends TestCase
{
    protected const API_KEY = 'ps_test_laravel';
    protected const WEBHOOK_SECRET = 'whsec_laravel';

    /**
     * @param Application $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app)
    {
        return [PeppolServiceProvider::class];
    }

    /**
     * @param Application $app
     *
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app)
    {
        return ['Peppol' => Peppol::class];
    }

    /**
     * @param Application $app
     *
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $config = $app->make('config');
        self::assertInstanceOf(Repository::class, $config);
        // The `web` middleware group encrypts cookies.
        $config->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
        $config->set('peppol.api_key', self::API_KEY);
        $config->set('peppol.base_url', 'https://sandbox.peppol.sh');
        $config->set('peppol.webhook_secret', self::WEBHOOK_SECRET);
    }

    protected function laravel(): Application
    {
        self::assertInstanceOf(Application::class, $this->app);

        return $this->app;
    }

    protected function configRepository(): Repository
    {
        $config = $this->laravel()->make('config');
        self::assertInstanceOf(Repository::class, $config);

        return $config;
    }

    /**
     * A valid `POST /v1/documents` body (the example of the README).
     *
     * @return array{company_id: string, type: 'invoice', number: string, issue_date: string, due_date: string, currency: string, from: array{name: string, tax_id: string, peppol_id: string}, to: array{name: string, tax_id: string, peppol_id: string}, lines: list<array{description: string, quantity: int, unit: string, unit_price: float, tax_rate: float}>, idempotency_key: string}
     */
    protected static function invoice(): array
    {
        return [
            'company_id' => 'com_1',
            'type' => 'invoice',
            'number' => 'INV-2026-001',
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'currency' => 'EUR',
            'from' => ['name' => 'Acme BV', 'tax_id' => 'BE0123456749', 'peppol_id' => '0208:0123456749'],
            'to' => ['name' => 'Globex NV', 'tax_id' => 'BE0987654394', 'peppol_id' => '0208:0987654394'],
            'lines' => [
                [
                    'description' => 'API integration services',
                    'quantity' => 1,
                    'unit' => 'C62',
                    'unit_price' => 500.0,
                    'tax_rate' => 21.0,
                ],
            ],
            'idempotency_key' => 'acme-INV-2026-001',
        ];
    }
}
