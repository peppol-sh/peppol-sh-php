# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-10-08

### Added

- `preceding_invoice` on `documents()->send`, `sendBatch`, and `get`: the
  preceding invoice reference. This is the earlier invoice that a document
  corrects, for example the invoice that a credit note credits. `number` is
  mandatory (BT-25). `issue_date` is optional (BT-26). The `PrecedingInvoice`
  array shape is in `PeppolSh\Generated\Types`.
- `amount_due` on `documents()->send`, `sendBatch`, and `get`: the amount due
  for payment (BT-115), for a document that is paid before it is sent. Use `0`
  for a fully prepaid document. The value must be from `0` up to the document
  total with VAT. The prepaid amount (BT-113) is the total less `amount_due`.
  `get` returns the field only when the document was created with one.

### Fixed

- `total`, `subtotal`, and `tax_total` on `documents()->get` and
  `documents()->list`: the API includes the line-level and the document-level
  allowances and charges in the total with VAT (BT-112), the total without VAT
  (BT-109), and the VAT total (BT-110). This is an API change: no SDK code
  changed.

## [0.1.0] - 2026-10-07

First version. The date replaces "Unreleased" when the `v0.1.0` tag is made.

### Added

- Initial `PeppolSh\Client`: API key auth, configurable base URL
  (`Client::SANDBOX_BASE_URL`, `Client::PRODUCTION_BASE_URL`), injectable
  transport (`http_client`), timeout for each attempt, and retries with
  exponential backoff. PHP 7.4 to 8.5, `ext-curl` and `ext-json` only.
- `health()` — `GET /v1/health`, and `signup()` — `POST /v1/signup`.
- `request()` and `requestRaw()` for an endpoint that has no resource method.
- Exception hierarchy in `PeppolSh\Exception`: `PeppolException`,
  `ApiException`, and one subclass per HTTP status (`ValidationException`,
  `AuthenticationException`, `PermissionException`, `NotFoundException`,
  `ConflictException`, `RateLimitException`, `ServerException`), plus
  `ConnectionException`, `TimeoutException`, and
  `SignatureVerificationException`.
- `PeppolSh\Generated\Types` — PHPDoc array shapes for requests and responses,
  generated from the OpenAPI spec.
- `documents()` — `send`, `sendBatch`, `list`, `get`, `history`, `ubl`,
  `listAttachments`, `getAttachment`.
- `companies()` — `create`, `list`, `get`, `update`.
- `webhooks()` — `list`, `create`, `get`, `delete`, `listDeliveries`, `test`,
  `rotateSecret`.
- `events()` — `list`.
- `lookup()` — `participant`, `dns`.
- `validate()` — `document`.
- `account()` — `get`, `createKey`, `revokeKey`, `listAuditEvents`, `getUsage`.
- `workspaces()` — `create`, `list`, `get`, `update`, `delete`, `listMembers`,
  `inviteMember`, `changeMemberRole`, `removeMember`, `transferOwnership`,
  `listAuditEvents`.
- `kyc()` — `get`, `uploadDocument`, `submit`.
- `PeppolSh\Pagination::iterate()` — yields the items of every page of a
  cursor-paginated list method.
- `PeppolSh\Webhook` — `verify()`, `verifySignature()`, and
  `signatureHeader()` for the `X-Peppol-Signature-V2` header.
- `PeppolSh\Testing\FakeHttpClient` — a transport for tests that replies from
  a queue, records each request, and has `assertSent`, `assertRequestCount`,
  and `assertNothingSent`.
- Laravel integration (Laravel 8 to 13, auto-discovered): `config/peppol.php`,
  the `Peppol` facade, a `PeppolSh\Client` singleton, the `peppol.webhook`
  route middleware, `Peppol::fake()`, and Laravel Boost AI guidelines.

[Unreleased]: https://github.com/peppol-sh/peppol-sh-php/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/peppol-sh/peppol-sh-php/releases/tag/v0.2.0
[0.1.0]: https://github.com/peppol-sh/peppol-sh-php/releases/tag/v0.1.0
