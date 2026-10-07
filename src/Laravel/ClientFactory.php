<?php

declare(strict_types=1);

namespace PeppolSh\Laravel;

use PeppolSh\Client;
use PeppolSh\Exception\PeppolException;

/**
 * Builds a `PeppolSh\Client` from the `peppol` config array. No state.
 *
 * @internal used by the service provider and the facade fake
 */
final class ClientFactory
{
    /** The API key `Peppol::fake()` uses in place of the configured key. */
    public const FAKE_API_KEY = 'ps_test_fake';

    private function __construct()
    {
    }

    /**
     * @param mixed                $config    the value of `config('peppol')`
     * @param array<string, mixed> $overrides client options that replace the config values
     *
     * @throws PeppolException when there is no API key or a config value is invalid
     */
    public static function make($config, array $overrides = []): Client
    {
        if (!is_array($config)) {
            $config = [];
        }

        $apiKey = $config['api_key'] ?? null;
        $apiKey = is_string($apiKey) ? trim($apiKey) : '';
        if ($apiKey === '') {
            throw new PeppolException(
                'The peppol.sh API key is not configured. Set PEPPOL_API_KEY in your .env file'
                . ' (config key "peppol.api_key"). If the config is cached, run "php artisan config:clear".'
            );
        }
        // Whitespace at the two ends is removed above. The client rejects
        // the same characters, but its message cannot name the .env key.
        if (preg_match('/[\x00-\x20\x7f]/', $apiKey) === 1) {
            throw new PeppolException(
                'The peppol.sh API key contains whitespace or a control character. Correct PEPPOL_API_KEY in'
                . ' your .env file (config key "peppol.api_key"). If the config is cached, run'
                . ' "php artisan config:clear".'
            );
        }

        $options = [];

        $baseUrl = $config['base_url'] ?? null;
        if ($baseUrl !== null && $baseUrl !== '') {
            $options['base_url'] = $baseUrl;
        }

        // Values from .env are strings: convert the numeric ones. Other
        // values go to the client as they are, and the client rejects them
        // with a clear message.
        $timeout = $config['timeout'] ?? null;
        if ($timeout !== null && $timeout !== '') {
            $options['timeout'] = is_string($timeout) && is_numeric($timeout) ? (float) $timeout : $timeout;
        }

        $maxRetries = $config['max_retries'] ?? null;
        if ($maxRetries !== null && $maxRetries !== '') {
            $options['max_retries'] = is_string($maxRetries) && preg_match('/\A\d+\z/', $maxRetries) === 1
                ? (int) $maxRetries
                : $maxRetries;
        }

        foreach ($overrides as $name => $value) {
            $options[$name] = $value;
        }

        return new Client($apiKey, $options);
    }
}
