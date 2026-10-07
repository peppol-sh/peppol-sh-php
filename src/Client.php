<?php

declare(strict_types=1);

namespace PeppolSh;

use PeppolSh\Exception\ApiException;
use PeppolSh\Exception\ConnectionException;
use PeppolSh\Exception\PeppolException;
use PeppolSh\Exception\TimeoutException;
use PeppolSh\HttpClient\CurlHttpClient;
use PeppolSh\HttpClient\HttpClientInterface;
use PeppolSh\HttpClient\HttpResponse;
use PeppolSh\Resources\Account;
use PeppolSh\Resources\Companies;
use PeppolSh\Resources\Documents;
use PeppolSh\Resources\Events;
use PeppolSh\Resources\Kyc;
use PeppolSh\Resources\Lookup;
use PeppolSh\Resources\Validate;
use PeppolSh\Resources\Webhooks;
use PeppolSh\Resources\Workspaces;

/**
 * The peppol.sh API client.
 *
 *     $peppol = new \PeppolSh\Client('ps_test_...', ['base_url' => \PeppolSh\Client::SANDBOX_BASE_URL]);
 *     $peppol->documents()->send([...]);
 *
 * The client does not select the environment from the key prefix: a sandbox
 * key (`ps_test_`) needs `base_url` set to `Client::SANDBOX_BASE_URL`.
 *
 * The configuration cannot change after construction and the class keeps no
 * static state, so one instance is safe to share for the life of a process.
 *
 * @phpstan-import-type GetHealthResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type SignupParams from \PeppolSh\Generated\Types
 * @phpstan-import-type SignupResponse from \PeppolSh\Generated\Types
 */
class Client
{
    public const PRODUCTION_BASE_URL = 'https://api.peppol.sh';
    public const SANDBOX_BASE_URL = 'https://sandbox.peppol.sh';
    public const DEFAULT_BASE_URL = self::PRODUCTION_BASE_URL;

    /** Default time limit for each attempt, in seconds. */
    public const DEFAULT_TIMEOUT = 30.0;

    /** Default number of extra attempts after a failure that can be retried. */
    public const DEFAULT_MAX_RETRIES = 2;

    /** Backoff is `BASE * 2^attempt` plus jitter, never more than `MAX` (seconds). */
    private const RETRY_BASE = 0.25;
    private const RETRY_MAX = 2.0;

    private const OPTION_KEYS = ['base_url', 'timeout', 'max_retries', 'http_client', 'sleep'];

    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var float */
    private $timeout;

    /** @var int */
    private $maxRetries;

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var callable(float): void */
    private $sleep;

    /** @var array<string, object> */
    private $resources = [];

    /**
     * @param string               $apiKey  `ps_test_...` for sandbox, `ps_live_...` for production
     * @param array<string, mixed> $options
     *                                      - `base_url` (string): default `Client::DEFAULT_BASE_URL`; a trailing slash is permitted
     *                                      - `timeout` (float): time limit for each attempt in seconds, default 30
     *                                      - `max_retries` (int): extra attempts after a failure that can be retried, default 2
     *                                      - `http_client` (HttpClientInterface): the transport, default `CurlHttpClient`
     *                                      - `sleep` (callable(float $seconds): void): how the client waits between retries
     *
     * @throws PeppolException when the API key is empty or contains whitespace or a control character,
     *                         or when an option is unknown or invalid
     */
    public function __construct(
        #[\SensitiveParameter]
        string $apiKey,
        array $options = []
    ) {
        if ($apiKey === '') {
            throw new PeppolException('The API key is required');
        }
        if (preg_match('/[\x00-\x20\x7f]/', $apiKey) === 1) {
            // The message does not show the key. The key is not trimmed: a
            // key with a stray character is a configuration error to repair.
            throw new PeppolException(
                'The API key contains whitespace or a control character (for example a trailing newline).'
                . ' Remove it from the value'
            );
        }
        $unknown = array_diff(array_keys($options), self::OPTION_KEYS);
        if ($unknown !== []) {
            throw new PeppolException(sprintf(
                'Unknown client option(s): %s. Permitted options: %s',
                implode(', ', $unknown),
                implode(', ', self::OPTION_KEYS)
            ));
        }

        $baseUrl = $options['base_url'] ?? self::DEFAULT_BASE_URL;
        if (!is_string($baseUrl) || preg_match('#^https?://[^/]#i', $baseUrl) !== 1) {
            throw new PeppolException('The "base_url" option must be an absolute http(s) URL');
        }

        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        // `!($timeout > 0)` also rejects NAN, for which each comparison is false.
        if ((!is_int($timeout) && !is_float($timeout)) || !is_finite((float) $timeout) || !($timeout > 0)) {
            throw new PeppolException('The "timeout" option must be a finite number of seconds larger than 0');
        }

        $maxRetries = $options['max_retries'] ?? self::DEFAULT_MAX_RETRIES;
        if (!is_int($maxRetries) || $maxRetries < 0) {
            throw new PeppolException('The "max_retries" option must be an integer of 0 or more');
        }

        $httpClient = $options['http_client'] ?? null;
        if ($httpClient !== null && !$httpClient instanceof HttpClientInterface) {
            throw new PeppolException('The "http_client" option must implement ' . HttpClientInterface::class);
        }

        $sleep = $options['sleep'] ?? null;
        if ($sleep !== null && !is_callable($sleep)) {
            throw new PeppolException('The "sleep" option must be a callable');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = (float) $timeout;
        $this->maxRetries = $maxRetries;
        $this->httpClient = $httpClient ?? new CurlHttpClient();
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1000000));
        };
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * The time limit for each attempt, in seconds.
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Account details, API keys, usage, and the account audit trail.
     */
    public function account(): Account
    {
        return $this->resource(Account::class);
    }

    /**
     * The companies (legal entities) you send for.
     */
    public function companies(): Companies
    {
        return $this->resource(Companies::class);
    }

    /**
     * Send documents and read their status, history, UBL, and attachments.
     */
    public function documents(): Documents
    {
        return $this->resource(Documents::class);
    }

    /**
     * The event log.
     */
    public function events(): Events
    {
        return $this->resource(Events::class);
    }

    /**
     * Workspace verification (KYC).
     */
    public function kyc(): Kyc
    {
        return $this->resource(Kyc::class);
    }

    /**
     * Peppol participant lookup (SMP and DNS).
     */
    public function lookup(): Lookup
    {
        return $this->resource(Lookup::class);
    }

    /**
     * Validate a document without sending it.
     */
    public function validate(): Validate
    {
        return $this->resource(Validate::class);
    }

    /**
     * Webhook endpoints and their deliveries.
     */
    public function webhooks(): Webhooks
    {
        return $this->resource(Webhooks::class);
    }

    /**
     * Workspaces and their members.
     */
    public function workspaces(): Workspaces
    {
        return $this->resource(Workspaces::class);
    }

    /**
     * `POST /v1/signup`: creates an account and returns a sandbox API key.
     * Public endpoint: no `Authorization` header is sent. The `api_key` in
     * the response is shown one time only.
     *
     * @param SignupParams $params
     *
     * @return SignupResponse
     *
     * @throws PeppolException
     */
    public function signup(array $params): array
    {
        $result = $this->request('POST', '/v1/signup', [], $params, false);

        return is_array($result) ? $result : [];
    }

    /**
     * `GET /v1/health`: liveness probe with a check for each dependency.
     *
     * @return GetHealthResponse
     *
     * @throws PeppolException
     */
    public function health(): array
    {
        $result = $this->request('GET', '/v1/health');

        return is_array($result) ? $result : [];
    }

    /**
     * Low-level request for an endpoint that has no resource method yet.
     * Returns the decoded JSON (an array) for a JSON response, the raw string
     * for a different content type, and null for an empty body.
     *
     * @param string                    $path  path relative to the base URL, for example `/v1/documents`
     * @param array<string, mixed>      $query query values; null entries are skipped
     * @param array<mixed>|null         $body  JSON body (an object, or a list); null sends no body, `[]` sends `{}`
     * @param bool                      $auth  false sends no `Authorization` header
     * @param bool                      $list  true sends the body as a JSON array for an endpoint that takes one:
     *                                         the keys are dropped and `[]` sends `[]`
     *
     * @return array<string, mixed>|string|null
     *
     * @throws PeppolException
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $auth = true,
        bool $list = false
    ) {
        $response = $this->requestRaw($method, $path, $query, $body, $auth, $list);
        $raw = $response->getBody();
        if ($response->getStatusCode() === 204 || $raw === '') {
            return null;
        }
        if (stripos($response->getHeader('content-type') ?? '', 'json') === false) {
            return $raw;
        }

        try {
            /** @var array<string, mixed>|scalar|null $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new PeppolException(
                sprintf('The response to %s %s is not valid JSON: %s', $method, $path, $e->getMessage()),
                0,
                $e
            );
        }

        // A JSON scalar is not an API response shape; give it back as text.
        return is_array($decoded) || $decoded === null ? $decoded : $raw;
    }

    /**
     * Same as `request()`, but returns the response without decoding the
     * body. Applies the same headers, retries, and error mapping.
     *
     * @param array<string, mixed> $query
     * @param array<mixed>|null    $body
     * @param bool                 $list  true sends the body as a JSON array (see `request()`)
     *
     * @throws ApiException        when the API returns a status that is not 2xx
     * @throws TimeoutException    when an attempt exceeds the time limit (not retried)
     * @throws ConnectionException when the request did not get to the API
     * @throws PeppolException     when the body cannot be encoded as JSON
     */
    public function requestRaw(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $auth = true,
        bool $list = false
    ): HttpResponse {
        $method = strtoupper($method);
        $url = $this->baseUrl . $path . self::buildQuery($query);

        $headers = [
            'accept' => 'application/json',
            'user-agent' => 'peppol-sh-php/' . Version::VERSION,
            'x-peppol-sdk' => 'peppol-sh/sdk-php/' . Version::VERSION,
        ];
        if ($auth) {
            $headers['authorization'] = 'Bearer ' . $this->apiKey;
        }
        $payload = null;
        if ($body !== null) {
            $headers['content-type'] = 'application/json';
            $payload = self::encodeBody($body, $list);
        }

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->httpClient->request($method, $url, $headers, $payload, $this->timeout);
            } catch (TimeoutException $e) {
                throw $e;
            } catch (ConnectionException $e) {
                if ($method === 'GET' && $attempt < $this->maxRetries) {
                    ($this->sleep)(self::backoff($attempt));
                    continue;
                }
                throw $e;
            }

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return $response;
            }

            $retryable = $status === 429 || ($status >= 500 && $method === 'GET');
            if ($retryable && $attempt < $this->maxRetries) {
                $retryAfter = ApiException::parseRetryAfter($response->getHeader('retry-after'));
                ($this->sleep)($retryAfter === null ? self::backoff($attempt) : min($retryAfter, self::RETRY_MAX));
                continue;
            }

            throw ApiException::fromResponse($response);
        }
    }

    /**
     * Replaces the API key with its prefix (`ps_test_...`) in the output of
     * `var_dump()`, `print_r()`, and Symfony VarDumper (`dump()` and `dd()`
     * in Laravel), also when the dumped value is a resource such as
     * `$client->documents()`.
     *
     * The keys are the internal names of the private properties
     * (`"\0PeppolSh\Client\0apiKey"`). PHP shows them as private properties.
     * Symfony VarDumper reads the private properties directly and then
     * applies `__debugInfo()`: only an entry with the internal name replaces
     * the real value there, so no VarDumper caster is necessary.
     *
     * This does NOT protect against `var_export()`, an `(array)` cast,
     * reflection, or a debugger: PHP has no hook for those. `serialize()`
     * throws (see `__serialize()`). Do not put the client in a log context.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $prefix = "\0" . self::class . "\0";

        return [
            $prefix . 'apiKey' => self::redact($this->apiKey),
            $prefix . 'baseUrl' => $this->baseUrl,
            $prefix . 'timeout' => $this->timeout,
            $prefix . 'maxRetries' => $this->maxRetries,
        ];
    }

    /**
     * The client holds the API key, so it cannot be serialized. A resource
     * holds the client, so it cannot be serialized either.
     *
     * @return array<string, mixed>
     *
     * @throws PeppolException always
     */
    public function __serialize(): array
    {
        throw new PeppolException('A PeppolSh\\Client cannot be serialized: it holds the API key');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws PeppolException always
     */
    public function __unserialize(array $data): void
    {
        throw new PeppolException('A PeppolSh\\Client cannot be unserialized');
    }

    /**
     * The environment prefix of the key and no secret character. A key that
     * has no known prefix shows nothing.
     */
    private static function redact(
        #[\SensitiveParameter]
        string $apiKey
    ): string {
        return preg_match('/\Aps_(?:test|live)_/', $apiKey, $match) === 1 ? $match[0] . '...' : '...';
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function resource(string $class): object
    {
        $existing = $this->resources[$class] ?? null;
        if ($existing instanceof $class) {
            return $existing;
        }
        $created = new $class($this);
        $this->resources[$class] = $created;

        return $created;
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function buildQuery(array $query): string
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            if (!is_scalar($value)) {
                throw new PeppolException(sprintf('The query value "%s" must be a string, number, or boolean', $key));
            }
            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return $pairs === [] ? '' : '?' . implode('&', $pairs);
    }

    /**
     * @param array<mixed> $body
     */
    private static function encodeBody(array $body, bool $list): string
    {
        if ($list) {
            // A PHP array with gaps in its keys encodes as a JSON object.
            $body = array_values($body);
        } elseif ($body === []) {
            // An empty PHP array encodes as `[]`; the API expects an object.
            return '{}';
        }

        try {
            return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new PeppolException('Could not encode the request body as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function backoff(int $attempt): float
    {
        $exponential = self::RETRY_BASE * (2 ** $attempt);
        $jitter = (mt_rand() / mt_getrandmax()) * self::RETRY_BASE;

        return min($exponential + $jitter, self::RETRY_MAX);
    }
}
