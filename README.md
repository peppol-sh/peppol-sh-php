# peppol-sh/sdk

Send e-invoices over the Peppol network with one API call, from plain PHP or
from Laravel. A PHP array in, an e-invoice out, delivered to the recipient's
access point.

[![Packagist](https://img.shields.io/packagist/v/peppol-sh/sdk.svg)](https://packagist.org/packages/peppol-sh/sdk)
[![PHP](https://img.shields.io/packagist/php-v/peppol-sh/sdk.svg)](https://packagist.org/packages/peppol-sh/sdk)
[![CI](https://github.com/peppol-sh/peppol-sh-php/actions/workflows/ci.yml/badge.svg)](https://github.com/peppol-sh/peppol-sh-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](./LICENSE)

The client is a thin wrapper over the [peppol.sh](https://peppol.sh) `/v1`
API. It runs on PHP 7.4 to 8.5, needs only `ext-curl` and `ext-json`, and has
a first-party [Laravel integration](#laravel) in the same package. Arrays in,
arrays out: every key is the `snake_case` name the JSON API uses, so the API
reference is also the SDK reference.

## Install

```bash
composer require peppol-sh/sdk
```

## Quickstart

Sign up at [peppol.sh](https://peppol.sh) — or with `POST /v1/signup` — and you
get a sandbox key (`ps_test_…`) straight away. Point the client at
`https://sandbox.peppol.sh`, create a company to send from, and send an invoice.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use PeppolSh\Client;

$peppol = new Client(getenv('PEPPOL_API_KEY'), [ // ps_test_…
    'base_url' => Client::SANDBOX_BASE_URL,      // omit for production
]);

$company = $peppol->companies()->create([
    'name' => 'Acme BV',
    'country' => 'BE',
    'company_registration_id' => '0123456749',
    'peppol_id' => '0208:0123456749', // 0208 = Belgian enterprise number
]);

$invoice = $peppol->documents()->send([
    'company_id' => $company['id'],
    'type' => 'invoice',
    'number' => 'INV-2026-001',
    'issue_date' => '2026-03-01',
    'due_date' => '2026-03-31',
    'currency' => 'EUR',
    'from' => [
        'name' => 'Acme BV',
        'tax_id' => 'BE0123456749',
        'peppol_id' => '0208:0123456749',
        'address' => [
            'street' => 'Keizerslaan 1',
            'city' => 'Brussels',
            'postal_code' => '1000',
            'country' => 'BE',
        ],
    ],
    'to' => [
        'name' => 'Globex NV',
        'tax_id' => 'BE0987654394',
        'peppol_id' => '0208:0987654394',
    ],
    'lines' => [
        [
            'description' => 'API integration services',
            'quantity' => 1,
            'unit' => 'C62', // UN/CEFACT unit code — C62 is "piece"
            'unit_price' => 500.0,
            'tax_rate' => 21.0,
        ],
    ],
    'payment_means' => ['method' => 'bank_transfer', 'iban' => 'BE68539007547034'],
    'idempotency_key' => 'acme-INV-2026-001',
]);

echo $invoice['id'], ' ', $invoice['status'], ' ', $invoice['url'], PHP_EOL;
// doc_a1b2c3d4 queued /v1/documents/doc_a1b2c3d4
```

Sending is asynchronous. Read the document back for its current status, or
subscribe to [webhooks](#webhooks) instead of polling.

```php
$current = $peppol->documents()->get($invoice['id'], [
    'company_id' => $company['id'],
]);
echo $current['status']; // queued | sending | delivered | failed

$timeline = $peppol->documents()->history($invoice['id']);
$ubl = $peppol->documents()->ubl($invoice['id']); // Send-ready UBL XML, a string
```

## Laravel

The package includes a Laravel integration for Laravel 8 to 13. Laravel finds
the service provider and the `Peppol` facade by package auto-discovery: there
is nothing to register.

Add the key to `.env`. A sandbox key needs the sandbox base URL.

```dotenv
PEPPOL_API_KEY=ps_test_...
PEPPOL_BASE_URL=https://sandbox.peppol.sh
```

| Variable | Default | What it does |
| --- | --- | --- |
| `PEPPOL_API_KEY` | *(required)* | Bearer key. |
| `PEPPOL_BASE_URL` | `https://api.peppol.sh` | Set `https://sandbox.peppol.sh` for a `ps_test_` key. |
| `PEPPOL_TIMEOUT` | `30` | Time limit for each attempt, in seconds. |
| `PEPPOL_MAX_RETRIES` | `2` | Extra attempts after a failure that can be retried. |
| `PEPPOL_WEBHOOK_SECRET` | *(none)* | Signing secret (`whsec_…`) for the `peppol.webhook` middleware. |
| `PEPPOL_WEBHOOK_TOLERANCE` | `300` | Permitted clock difference for a webhook signature, in seconds. `0` disables the time check. |

To change the config file, publish it to `config/peppol.php`:

```bash
php artisan vendor:publish --tag=peppol-config
```

Use the facade, or inject `PeppolSh\Client`. The container holds one client
(a singleton), so the two give you the same instance.

```php
use PeppolSh\Client;
use PeppolSh\Laravel\Facades\Peppol;

// Facade
$invoice = Peppol::documents()->send([
    'company_id' => 'com_abc123',
    'type' => 'invoice',
    'number' => 'INV-2026-001',
    'issue_date' => '2026-03-01',
    'currency' => 'EUR',
    'from' => ['name' => 'Acme BV', 'tax_id' => 'BE0123456749'],
    'to' => ['name' => 'Globex NV', 'tax_id' => 'BE0987654394', 'peppol_id' => '0208:0987654394'],
    'lines' => [
        ['description' => 'Support', 'quantity' => 2, 'unit' => 'C62', 'unit_price' => 75, 'tax_rate' => 21],
    ],
    'idempotency_key' => 'acme-INV-2026-001',
]);

// Dependency injection, for example in a queued job
public function handle(Client $peppol): void
{
    $peppol->documents()->get($this->documentId, ['company_id' => $this->companyId]);
}
```

### Webhook middleware

The `peppol.webhook` route middleware
(`PeppolSh\Laravel\Http\Middleware\VerifyWebhookSignature`) verifies the raw
request body against the `X-Peppol-Signature-V2` header with
`PEPPOL_WEBHOOK_SECRET`. A request with a missing or bad signature, or a
timestamp out of tolerance, gets a `400` response and does not get to your
controller. If the secret is not configured, the middleware throws a
`PeppolException` (HTTP 500), because that is a server configuration error.

```php
// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/peppol', function (Request $request) {
    if ($request->input('type') === 'document.delivered') {
        // $request->input('data.document_id'), $request->input('data.status')
    }

    return response()->noContent();
})->middleware('peppol.webhook');
```

Put the route in `routes/api.php`. If you put it in `routes/web.php`, exclude
it from CSRF verification.

### Tests in Laravel

`Peppol::fake()` replaces the client in the container with one that sends
nothing, and returns the `FakeHttpClient` to assert against. The fake client
needs no API key, never sends the configured one (each recorded request has
`Bearer ps_test_fake`), and does not retry. Call it at the start of the test: an
object that got the real client injected before the call keeps the real one.

```php
use PeppolSh\Laravel\Facades\Peppol;
use PeppolSh\Testing\FakeHttpClient;

public function test_it_sends_the_invoice(): void
{
    $fake = Peppol::fake([
        FakeHttpClient::json(['id' => 'doc_abc123', 'status' => 'queued', 'url' => '/v1/documents/doc_abc123'], 202),
    ]);

    $this->post('/invoices/42/send')->assertOk();

    $fake->assertSent('POST', '/v1/documents');
}
```

### Laravel Boost

The package ships AI guidelines for [Laravel Boost](https://github.com/laravel/boost),
so a coding agent in your Laravel project gets the correct method names and
payload keys.

## Authentication and environments

Every request sends `Authorization: Bearer <api key>`. Keys are long-lived and
carry their environment in the prefix.

| Key prefix | Base URL | Delivery |
| --- | --- | --- |
| `ps_test_` | `https://sandbox.peppol.sh` | Sandbox — delivered by email, never touches the real Peppol network |
| `ps_live_` | `https://api.peppol.sh` (default) | Production — delivered over Peppol |

The client does not select the environment from the key. `base_url` defaults
to `https://api.peppol.sh`, so a sandbox key needs the sandbox URL set
explicitly. A trailing slash is tolerated.

```php
$peppol = new \PeppolSh\Client(getenv('PEPPOL_API_KEY'), [
    'base_url' => getenv('PEPPOL_BASE_URL') ?: \PeppolSh\Client::DEFAULT_BASE_URL,
    'timeout' => 15.0,
    'max_retries' => 3,
]);

print_r($peppol->health());
// ['status' => 'ok', 'version' => '2.1.0', 'environment' => 'production', 'checks' => ['db' => 'ok']]
```

### Client options

The API key is the first constructor argument. The constructor throws
`PeppolSh\Exception\PeppolException` if the key is empty, if an option has an
invalid value, or if an option name is unknown.

| Option | Default | What it does |
| --- | --- | --- |
| `base_url` | `https://api.peppol.sh` | Sandbox, production, or a local API. |
| `timeout` | `30.0` | Time limit for each attempt, in seconds. On expiry the call throws `TimeoutException`. |
| `max_retries` | `2` | Extra attempts after a retryable failure. |
| `http_client` | `CurlHttpClient` | Bring your own transport: an object that implements `PeppolSh\HttpClient\HttpClientInterface`. For a proxy or a CA bundle, pass `new CurlHttpClient([CURLOPT_PROXY => '...'])`. |
| `sleep` | `usleep` | A `callable(float $seconds): void` that replaces the backoff wait. Useful in tests. |

The defaults are constants on the client: `Client::DEFAULT_BASE_URL`,
`Client::PRODUCTION_BASE_URL`, `Client::SANDBOX_BASE_URL`,
`Client::DEFAULT_TIMEOUT`, and `Client::DEFAULT_MAX_RETRIES`. The package
version the client reports in its `x-peppol-sdk` request header is
`PeppolSh\Version::VERSION`.

The configuration cannot change after construction and the client keeps no
static state, so one instance is safe to share for the life of a process.

## Error handling

Every non-2xx response becomes a typed exception carrying the API's canonical
envelope — `{ error: { type, code, message, param?, details? } }`. Branch on
`getErrorCode()`, never on the message.

```php
use PeppolSh\Exception\ApiException;
use PeppolSh\Exception\ConnectionException;
use PeppolSh\Exception\RateLimitException;
use PeppolSh\Exception\TimeoutException;
use PeppolSh\Exception\ValidationException;

try {
    $peppol->documents()->get('doc_a1b2c3d4', ['company_id' => 'com_abc123']);
} catch (ValidationException $e) {
    error_log(sprintf('%s on %s: %s', $e->getErrorCode(), $e->getParam(), $e->getMessage()));
} catch (RateLimitException $e) {
    error_log(sprintf('Rate limited, retry in %ss', $e->getRetryAfter() ?? 1));
} catch (ApiException $e) {
    error_log(sprintf('HTTP %d %s (req %s)', $e->getStatusCode(), $e->getErrorCode(), $e->getRequestId()));
} catch (TimeoutException $e) {
    error_log(sprintf('Timed out after %ss', $e->getTimeout()));
} catch (ConnectionException $e) {
    error_log('Could not reach the API: ' . $e->getMessage());
}
```

All classes are in the `PeppolSh\Exception` namespace.

| Class | Thrown when |
| --- | --- |
| `ValidationException` | HTTP 400 or 422 — the body or query failed validation. Read `getParam()` for the field. |
| `AuthenticationException` | HTTP 401 — the key is missing, malformed, or unknown. |
| `PermissionException` | HTTP 403 — authenticated, but not allowed to do this. |
| `NotFoundException` | HTTP 404 — no such resource, or not visible to this key. |
| `ConflictException` | HTTP 409 — the request conflicts with current state. |
| `RateLimitException` | HTTP 429 — too many requests. `getRetryAfter()` gives the `Retry-After` seconds when the API sent them. |
| `ServerException` | HTTP 5xx — a valid request the API failed to process. |
| `ApiException` | Any other non-2xx status, for example 402 when the workspace is out of credits. Base class of all of the above. |
| `TimeoutException` | No answer within `timeout`. `getTimeout()` gives the limit in seconds. |
| `ConnectionException` | The request never arrived: DNS, TLS, or socket failure. |
| `SignatureVerificationException` | `Webhook::verify()` found a malformed header, no matching signature, or a timestamp out of tolerance. |
| `PeppolException` | Base class of everything the SDK throws, including client-side misconfiguration such as an empty API key. |

`ApiException` and its subclasses expose `getStatusCode()`, `getErrorType()`,
`getErrorCode()`, `getParam()`, `getDetails()`, `getRawBody()`, `getHeaders()`,
and `getRequestId()` (from the `x-request-id` response header — quote it in
support requests). The built-in `getCode()` is the HTTP status, because PHP
exception codes are integers.

## Pagination

List endpoints answer with `['data' => [...], 'has_more' => bool,
'next_cursor' => string|null]`. `PeppolSh\Pagination::iterate()` walks every
page for you and yields the items one at a time. It fetches a page only when
the loop gets to it.

```php
use PeppolSh\Pagination;

$documents = Pagination::iterate([$peppol->documents(), 'list'], [
    'company_id' => 'com_abc123',
    'status' => 'delivered',
    'limit' => 50,
]);

foreach ($documents as $doc) {
    echo $doc['id'], ' ', $doc['status'], ' ', $doc['total'], PHP_EOL;
}

// A list method that takes an id first:
$deliveries = Pagination::iterate(function (array $params) use ($peppol) {
    return $peppol->webhooks()->listDeliveries('wh_abc123', $params);
});
```

To page by hand, pass `next_cursor` back as `cursor` while `has_more` is true.

`documents()->list`, `events()->list`, `webhooks()->listDeliveries`, and both
`listAuditEvents` methods page this way. `companies()->list`,
`webhooks()->list`, and `workspaces()->list` return the full set in a `data`
envelope.

## Retries and timeouts

Each attempt gets its own `timeout` budget; on expiry the call throws
`TimeoutException` and is not retried. What is retried, up to `max_retries`
extra attempts:

| Failure | Retried |
| --- | --- |
| HTTP 429 | Yes, for every method — the request was rejected before it was processed |
| HTTP 5xx | `GET` only |
| Network failure (`ConnectionException`) | `GET` only |
| HTTP 4xx other than 429 | Never |
| Timeout | Never |

Backoff is exponential with jitter, starting at 250 ms and capped at 2 s. A
`Retry-After` header in seconds wins over the computed delay (still capped at
2 s); the HTTP-date form is ignored. Set `'max_retries' => 0` to handle
failures yourself.

### Send is idempotent

The SDK does not make an idempotency key for you. Put `idempotency_key` in the
`documents()->send` body and a repeated call is safe. The API answers `202`
when it queues a new send and `200` when it replays an existing one; both
carry the same shape (`id`, `status`, `url`), so you always get the `id` of
the record that exists. A retried send therefore never produces a duplicate
invoice on the network.

## API surface

Nine resources hang off the client, one per `/v1` area. Each method takes
arrays and returns the decoded JSON response as an array, unless the table
says otherwise.

**`$peppol->documents()`**

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `send(array $params)` | `POST /v1/documents` | Create and send one document |
| `sendBatch(array $params)` | `POST /v1/documents/batch` | Send up to 100 documents for one company; results map 1:1 to the input |
| `list(array $params)` | `GET /v1/documents` | One cursor page of a company's documents |
| `get(string $id, array $params)` | `GET /v1/documents/{id}` | One document, scoped to its company (`company_id` is required) |
| `history(string $id)` | `GET /v1/documents/{id}/history` | Delivery timeline: created, validated, queued, sending, delivered, failed |
| `ubl(string $id)` | `GET /v1/documents/{id}/ubl` | The stored Send-ready UBL XML, as a string |
| `listAttachments(string $id)` | `GET /v1/documents/{id}/attachments` | Attachment metadata |
| `getAttachment(string $id, string $attachmentId)` | `GET /v1/documents/{id}/attachments/{att_id}` | One attachment's raw bytes, as a binary string |

**`$peppol->companies()`**

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `create(array $params)` | `POST /v1/companies` | Create a company in the caller's workspace |
| `list()` | `GET /v1/companies` | Every company in the workspace, newest first |
| `get(string $id)` | `GET /v1/companies/{id}` | Full details for one company |
| `update(string $id, array $params)` | `PATCH /v1/companies/{id}` | Partial update; owners and admins only |

**`$peppol->webhooks()`**

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `list()` | `GET /v1/webhooks` | Every webhook in the workspace |
| `create(array $params)` | `POST /v1/webhooks` | Register an endpoint; returns the signing secret once |
| `get(string $id)` | `GET /v1/webhooks/{id}` | One webhook, without the secret |
| `delete(string $id)` | `DELETE /v1/webhooks/{id}` | Retire a webhook |
| `listDeliveries(string $id, array $params = [])` | `GET /v1/webhooks/{id}/deliveries` | One page of the delivery log |
| `test(string $id)` | `POST /v1/webhooks/{id}/test` | Dispatch a synthetic `webhook.test` event |
| `rotateSecret(string $id)` | `POST /v1/webhooks/{id}/rotate-secret` | Mint a new secret, with a 24-hour overlap |

**`$peppol->workspaces()`**

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `create(array $params)` | `POST /v1/workspaces` | Create a workspace owned by the caller |
| `list()` | `GET /v1/workspaces` | Every workspace the account belongs to |
| `get(string $id)` | `GET /v1/workspaces/{id}` | One workspace, with the caller's role |
| `update(string $id, array $params)` | `PATCH /v1/workspaces/{id}` | Rename or reconfigure; owners and admins only |
| `delete(string $id)` | `DELETE /v1/workspaces/{id}` | Delete a workspace; owners only |
| `listMembers(string $id)` | `GET /v1/workspaces/{id}/members` | Every member and their role |
| `inviteMember(string $id, array $params)` | `POST /v1/workspaces/{id}/members` | Add a member; owners and admins only |
| `changeMemberRole(string $id, string $accountId, array $params)` | `PATCH /v1/workspaces/{id}/members/{accountId}` | Change a role; owners only |
| `removeMember(string $id, string $accountId)` | `DELETE /v1/workspaces/{id}/members/{accountId}` | Remove a member and revoke their keys for this workspace |
| `transferOwnership(string $id, array $params)` | `POST /v1/workspaces/{id}/transfer-ownership` | Promote another account to owner; the caller becomes admin |
| `listAuditEvents(string $id, array $params = [])` | `GET /v1/workspaces/{id}/audit` | Audit events for this workspace |

**`$peppol->account()`, `$peppol->kyc()`, `$peppol->events()`,
`$peppol->lookup()`, `$peppol->validate()`, and the client itself**

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `account()->get()` | `GET /v1/account` | Profile, key prefixes, usage totals |
| `account()->createKey(array $params = [])` | `POST /v1/account/keys` | Mint an extra key; the full key is returned once |
| `account()->revokeKey(string $prefix)` | `DELETE /v1/account/keys/{prefix}` | Revoke a key permanently |
| `account()->listAuditEvents(array $params = [])` | `GET /v1/account/audit` | Audit events across every workspace joined |
| `account()->getUsage(array $params = [])` | `GET /v1/account/usage` | Daily documents and API calls |
| `kyc()->get()` | `GET /v1/kyc` | Status, attestation, documents, and what is still missing |
| `kyc()->uploadDocument(array $params)` | `POST /v1/kyc/documents` | Upload one document as base64 (10 MB decoded max) |
| `kyc()->submit(array $params)` | `POST /v1/kyc/submit` | Submit the workspace for review |
| `events()->list(array $params = [])` | `GET /v1/events` | The workspace event feed behind webhook deliveries, newest first |
| `lookup()->participant(string $peppolId, array $params = [])` | `GET /v1/lookup/{peppol_id}` | Resolve a participant through its SMP: document types and AS4 endpoints |
| `lookup()->dns(string $peppolId, array $params = [])` | `GET /v1/lookup/{peppol_id}/dns` | The NAPTR/SMP DNS layer only |
| `validate()->document(array $params)` | `POST /v1/validate` | Check a payload without creating or sending anything |
| `health()` | `GET /v1/health` | Liveness probe with per-dependency checks |
| `signup(array $params)` | `POST /v1/signup` | Create an account and get a sandbox key. Unauthenticated: no `Authorization` header is sent |

The client also exposes `request()` and `requestRaw()`, the same transport
the resource methods use, so an endpoint the SDK does not wrap yet is still
one call away with the auth, retry, timeout, and error handling described
above:

```php
$result = $peppol->request('GET', '/v1/documents', ['company_id' => 'com_abc123', 'limit' => 10]);
```

## Types and static analysis

The SDK supports PHP 7.4, so it has no typed value objects: requests and
responses are plain arrays. The types are in the PHPDoc instead. The array
shapes are generated from the API's OpenAPI spec into
`PeppolSh\Generated\Types`, and regenerated whenever the spec changes. There
is one alias for each schema of the spec (`Company`, `Workspace`, …), and each
operation has `<Operation>Params` (JSON body), `<Operation>Query` (query
parameters), and `<Operation>Response` where they apply — for example
`SendDocumentParams` and `SendDocumentResponse`. The resource methods use
these aliases in their `@param` and `@return` tags.

PhpStorm reads these shapes for key completion, and PHPStan checks your
arrays against them. Because the types are PHPDoc and not syntax, they
work the same on every supported PHP version. To use an alias in your own
code, import it in the docblock of your class:

```php
/**
 * @phpstan-import-type SendDocumentParams from \PeppolSh\Generated\Types
 */
final class InvoiceMapper
{
    /**
     * @return SendDocumentParams
     */
    public function toPeppol(Invoice $invoice): array
    {
        // ...
    }
}
```

The API can add fields at any time: a shape lists the known keys only.

Two quirks of generation: response keys are mostly optional, because the spec
marks few of them required — check them or use `??`. Request fields with a
schema default (`type`, `currency`, `unit`, `payment_means.method`) are filled
in by the API when you leave them out.

## Webhooks

Register an endpoint and the API posts document events to it — no polling.

```php
$hook = $peppol->webhooks()->create([
    'url' => 'https://example.com/hooks/peppol',
    'events' => ['document.delivered', 'document.failed', 'credits.low'],
]);

echo $hook['secret']; // whsec_… — shown once, store it now
```

Each delivery carries `X-Peppol-Signature-V2` (an HMAC-SHA256 signature over
the timestamp and the raw body), `X-Peppol-Timestamp`, `X-Peppol-Event`, and
`X-Peppol-Delivery-Id`. `PeppolSh\Webhook::verify()` checks the signature in
constant time, rejects a timestamp more than `$tolerance` seconds (default
300) from the local clock, and returns the decoded event.

```php
use PeppolSh\Exception\SignatureVerificationException;
use PeppolSh\Webhook;

$rawBody = file_get_contents('php://input');

try {
    $event = Webhook::verify(
        $rawBody,
        $_SERVER['HTTP_X_PEPPOL_SIGNATURE_V2'] ?? '',
        getenv('PEPPOL_WEBHOOK_SECRET')
    );
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}

if ($event['type'] === 'document.delivered') {
    // $event['data']['document_id'], $event['data']['status']
}

http_response_code(204);
```

Always pass the body bytes as received — re-encoding a decoded array breaks
the HMAC — and deduplicate on `X-Peppol-Delivery-Id`, which is stable across
retries. During a secret rotation the header carries one signature for each
active secret, and one match is sufficient. `Webhook::verifySignature()` does
the check without decoding the body. The header names are constants on the
class (`Webhook::SIGNATURE_HEADER`, `Webhook::DELIVERY_ID_HEADER`, …).

In Laravel, use the [`peppol.webhook` middleware](#webhook-middleware) instead.
The rotation overlap and the retry schedule are documented at
<https://peppol.sh/docs>. `webhooks()->test($id)` sends a synthetic event to
check your receiver; `webhooks()->listDeliveries($id)` shows every attempt.

## Testing your integration

`PeppolSh\Testing\FakeHttpClient` is a transport that sends nothing. It
replies from a queue and records every request, so your tests can run without
the network.

```php
use PeppolSh\Client;
use PeppolSh\Testing\FakeHttpClient;

$http = new FakeHttpClient([
    FakeHttpClient::json(['id' => 'doc_abc123', 'status' => 'queued', 'url' => '/v1/documents/doc_abc123'], 202),
    FakeHttpClient::error(404, 'document_not_found', 'Document not found', 'not_found'),
]);
$peppol = new Client('ps_test_x', ['http_client' => $http]);

$peppol->documents()->send(['company_id' => 'com_abc123', 'number' => 'INV-2026-001']);

$http->assertSent('POST', '/v1/documents');
$http->assertRequestCount(1);
$sent = $http->getLastRequest()->getJson(); // the request body, decoded
```

A queue entry is an `HttpResponse`, a `Throwable` (it is thrown, for example a
`ConnectionException`), or a callable that receives the `RecordedRequest` and
returns an `HttpResponse`. Entries are used in order; a request with an empty
queue throws a `LogicException`. Add entries later with `push()`.

| Assertion or accessor | What it does |
| --- | --- |
| `assertSent(string $method, string $path)` | Fails when no request with this method and path was sent. |
| `assertRequestCount(int $expected)` | Fails when the number of requests is different. |
| `assertNothingSent()` | Fails when a request was sent. |
| `getRequests()`, `getLastRequest()` | The recorded requests: `getMethod()`, `getPath()`, `getQuery()`, `getHeader()`, `getJson()`. |

The assertions throw a PHPUnit failure when PHPUnit is loaded, and a
`RuntimeException` in other test runners. To test a webhook receiver, sign a
fixture body with `Webhook::signatureHeader($rawBody, $secret)`. In Laravel,
use [`Peppol::fake()`](#tests-in-laravel).

## Requirements

PHP 7.4 or later (tested on 7.4 to 8.5) with `ext-curl` and `ext-json`. Zero
other runtime dependencies. The Laravel integration works with Laravel 8 to
13 and loads only when Laravel is installed. To use a different HTTP stack,
or to go through a proxy, pass your own transport with the `http_client`
option.

## Documentation

- Guides and API reference — <https://peppol.sh/docs>
- OpenAPI spec — <https://api.peppol.sh/v1/openapi.json>
- Changelog — [CHANGELOG.md](./CHANGELOG.md)

## Contributing

This repository is a read-only mirror. The SDK is developed in a private
monorepo next to the API and its OpenAPI contract, so an API change and its
client update land in one commit and are tested together. Pull requests are
still welcome here: maintainers apply accepted changes upstream, and they flow
back with the next mirror push — your change ships, but not under the commit
hash you pushed, and mirror pushes rewrite history, so do not build long-lived
branches here. Issues and feature requests belong on this repository.

## License

MIT © e-invoice bv
