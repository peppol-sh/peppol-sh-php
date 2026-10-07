# AGENTS.md

Guidance for AI coding agents that use or change `peppol-sh/sdk`, the PHP SDK
for the [peppol.sh](https://peppol.sh) Peppol e-invoicing API. `README.md` has
the full reference; this file has the rules that prevent the common mistakes.

## Use the PHP SDK

```bash
composer require peppol-sh/sdk
```

```php
$peppol = new \PeppolSh\Client(getenv('PEPPOL_API_KEY'), [
    'base_url' => \PeppolSh\Client::SANDBOX_BASE_URL, // omit for production
]);
```

- **Auth is an API key**, sent as `Authorization: Bearer <key>`. There is no
  OAuth. `(new \PeppolSh\Client('unused'))->signup(['email' => $email])` calls
  `POST /v1/signup` without the key and returns a sandbox key once.
- **The key prefix selects the environment, the client does not.** A
  `ps_test_` key needs `'base_url' => Client::SANDBOX_BASE_URL`
  (`https://sandbox.peppol.sh`; delivery by email, no real Peppol network). A
  `ps_live_` key uses the default `https://api.peppol.sh`. Never send test
  data with a live key.
- **Read the key from the environment.** Do not write it into source files.
- **Arrays in, arrays out.** The keys are the `snake_case` names of the JSON
  API. There are no model classes and no camelCase options in a request body.
- **Resources are methods, not properties:** `$peppol->documents()->send()`,
  not `$peppol->documents->send()`.
- **Put `idempotency_key` in the `documents()->send` body** (for example the
  invoice number), so a retry does not send the invoice twice. The PHP SDK
  does not make one for you.
- **Do not invent methods.** The complete surface is below. There is no
  `invoices()` resource.

| Resource | Methods |
|---|---|
| `$peppol` | `signup`, `health`, `request`, `requestRaw` |
| `$peppol->companies()` | `create`, `list`, `get`, `update` |
| `$peppol->documents()` | `send`, `sendBatch`, `list`, `get`, `history`, `ubl`, `listAttachments`, `getAttachment` |
| `$peppol->validate()` | `document` |
| `$peppol->lookup()` | `participant`, `dns` |
| `$peppol->webhooks()` | `list`, `create`, `get`, `delete`, `listDeliveries`, `test`, `rotateSecret` |
| `$peppol->events()` | `list` |
| `$peppol->kyc()` | `get`, `uploadDocument`, `submit` |
| `$peppol->account()` | `get`, `createKey`, `revokeKey`, `listAuditEvents`, `getUsage` |
| `$peppol->workspaces()` | `create`, `list`, `get`, `update`, `delete`, `listMembers`, `inviteMember`, `changeMemberRole`, `removeMember`, `transferOwnership`, `listAuditEvents` |

Each failure is a `PeppolSh\Exception\PeppolException` subclass
(`ValidationException`, `AuthenticationException`, `RateLimitException`, …).
Catch the class and read `getErrorCode()`, not the message text. The client
retries retryable failures itself (`max_retries`, default 2), so do not add a
second retry loop around it.

Helpers: `PeppolSh\Pagination::iterate()` walks a cursor-paginated list;
`PeppolSh\Webhook::verify($rawBody, $signatureHeader, $secret)` verifies the
`X-Peppol-Signature-V2` header and returns the event;
`PeppolSh\Testing\FakeHttpClient` is the transport for tests.

In Laravel (8 to 13, auto-discovered): set `PEPPOL_API_KEY` and
`PEPPOL_BASE_URL` in `.env`, call `Peppol::documents()->send([...])` through
the `PeppolSh\Laravel\Facades\Peppol` facade or inject `PeppolSh\Client`, put
the `peppol.webhook` middleware on the webhook route, and use `Peppol::fake()`
in tests.

## Change the PHP SDK

This repository is a read-only mirror: each push overwrites it from the
`packages/sdk-php` folder of the private peppol.sh monorepo. Open an issue or
a pull request here; a maintainer applies it upstream.

```bash
composer install
composer check      # lint + analyse + test
composer lint       # php-cs-fixer, no changes (composer lint:fix to fix)
composer analyse    # phpstan, level max, PHP 7.4 to 8.5
composer test       # phpunit
```

### Layout

| Path | Contents |
|---|---|
| `src/Client.php` | Constructor options, headers, retries, error mapping, `request()` / `requestRaw()` |
| `src/Resources/` | One class for each API area. A resource extends `AbstractResource` and never calls cURL |
| `src/Exception/` | `PeppolException` and its subclasses |
| `src/HttpClient/` | `HttpClientInterface`, `HttpResponse`, and the default `CurlHttpClient` |
| `src/Pagination.php`, `src/Webhook.php` | Static helpers |
| `src/Testing/` | `FakeHttpClient` and `RecordedRequest`, public test helpers |
| `src/Generated/Types.php` | Generated PHPDoc array shapes. Do not edit |
| `src/Laravel/`, `config/peppol.php`, `resources/boost/` | The Laravel integration and its Laravel Boost guidelines |
| `src/Version.php` | `Version::VERSION`, the only place where the code knows its version |
| `tests/`, `tests/Laravel/` | PHPUnit tests |
| `scripts/generate-types.ts` | The type generator (monorepo only) |

### PHP 7.4 to 8.5 compatibility

The same source must parse and run on every version from 7.4 to 8.5. PHPStan
checks both ends (`phpVersion` min 70400, max 80599). Do not use syntax or
functions that came after 7.4:

- No constructor property promotion, enums, `readonly`, union types, named
  arguments, `match`, or the nullsafe operator (`?->`).
- No `mixed` type declaration. Use a `@param mixed` / `@return mixed` docblock.
- No trailing comma in a parameter list. A trailing comma in a multi-line
  array is correct.
- No `str_contains`, `str_starts_with`, or `str_ends_with`.
- Always write a nullable type as `?T`, also for a parameter with a `null`
  default. An implicit nullable type is deprecated in PHP 8.4.
- Do not call `curl_close()`. It is deprecated in PHP 8.5 and does nothing
  from PHP 8.0.
- No dynamic properties. Declare each property (with a `@var` docblock).
- Put an attribute alone on its own line. `#[` starts a comment in PHP 7, so
  code on the same line after the attribute is lost.

### Rules

- **Zero runtime dependencies.** `require` in `composer.json` has only `php`,
  `ext-curl`, and `ext-json`. Laravel is not a requirement: the Laravel
  classes load only when Laravel is installed. Keep it so.
- **Parity with the TypeScript SDK.** The PHP SDK has the same resources and
  the same method names as the TypeScript SDK (`packages/sdk-typescript` in
  the monorepo). A method that is added, renamed, or removed in one is
  changed in the other in the same pull request. The deliberate PHP extras
  are `Webhook`, `Pagination`, and the Laravel integration.
- **Types are generated.** From the monorepo root, run
  `bun packages/sdk-php/scripts/generate-types.ts`. It reads
  `apps/api/src/openapi.yaml` and writes `src/Generated/Types.php`. The output
  is committed. Never edit it by hand.
- **Two drift tests skip in the mirror.** The route-coverage test
  (`tests/RouteCoverageTest.php`: each `operationId` in the OpenAPI spec maps
  to a method of the PHP SDK, or is excluded with a reason) and the
  generated-types drift test (the committed `Types.php` equals the generator
  output) need files that exist only in the monorepo. They skip themselves in
  the mirror. A skip there is correct; a skip in the monorepo is a defect.
- **No test calls the network.** Use `FakeHttpClient`. The cURL transport
  tests use the local server in `tests/Fixtures/server.php`. Add a test with
  each new method.
- The Laravel tests skip themselves when Laravel is not installed.
- Add each user-visible change to `CHANGELOG.md`.

### Mirror and release

The monorepo is the source of truth. CI force-pushes `packages/sdk-php` to
<https://github.com/peppol-sh/peppol-sh-php>, so commit hashes there change
and a commit made directly on the mirror is lost.

To release:

1. Change `Version::VERSION` in `src/Version.php` and add the version heading
   to `CHANGELOG.md` with a date (`## [X.Y.Z] - YYYY-MM-DD`).
   `tests/VersionTest.php` fails if the two are different.
2. Merge to `main` in the monorepo.

The mirror workflow in the monorepo (`.github/workflows/mirror-packages.yml`)
then pushes the package and tags `vX.Y.Z` on the mirror. It makes the tag only
when the tag does not exist and `CHANGELOG.md` has a dated heading for the
version, so a merged version change with a dated heading IS a public release.

There is no publish step and no registry token: Packagist reads the tag. The
release workflow on the mirror only checks that the tag equals
`Version::VERSION`, runs the tests, and makes the GitHub Release.

### Not in v0.1, on purpose

Do not add these without a decision from a maintainer:

- No Laravel controller, routes, events, or artisan commands. The Laravel
  integration is the config, the service provider, the facade, the webhook
  middleware, and the fake.
- No PSR-18 adapter. A different transport implements `HttpClientInterface`.
- No automatic idempotency keys. The caller puts `idempotency_key` in the
  `documents()->send` body.
