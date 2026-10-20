# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `DocumentEvent`, the item type of `documents()->history()`, has the fields
  the API sends: `id`, `type`, `from_status`, `to_status`, `message` and
  `created_at`. The old `event`, `timestamp` and `detail` fields were never
  sent (#418).
- `created_at`, `sent_at` and `delivered_at` of a document, `created_at` of a
  history event, and `created_at` and `updated_at` of a company are RFC 3339
  date-times (`2026-10-20T08:25:00Z`), as the types said. The API sent
  `2026-10-20 08:25:00` before (#418).
- `created_at` of a webhook, a workspace, a workspace member, an account,
  an API key, a KYC document, a document attachment, an event and an audit
  event, and `updated_at` of a workspace and `last_used_at` of an API key,
  are RFC 3339 date-times too. The API sent `2026-10-20 08:25:00` before
  (#418).
- `documents()->get()` gives the `sandbox` flag and the `events` of the
  document, which the API always sent. `from_status` of an event is a
  document status (#418).
- `health()` gives `worker_version` (`id`, `created_at`), the Worker version
  that served the request, or `null` (#418).
- The API now answers `400 invalid_body` (and not `500`) on
  `companies()->create()` and `companies()->update()` when the body is not
  valid JSON or not a JSON object (#418).

## [0.4.0] - 2026-10-20

### Changed

- The `Party` type (the `from` and `to` of `documents()->send()` and
  `documents()->sendBatch()`) has an optional `tax_id`, as in the API: a party
  needs a `tax_id` only when it has no `peppol_id`. PHPStan now accepts a
  party with only a `peppol_id` (for example a Norwegian company with no VAT
  number) (#425).
- The `ErrorObject` type has an optional `request_id` (the `cf-ray` of the
  request) on an `internal_error`. Give it to us when you report the error
  (#421).
- The API now answers `400 invalid_document_body` (and not `500`) on
  `documents()->send()` and `documents()->sendBatch()` when `number`, `from`,
  `to`, `from.name`, `to.name`, or the `tax_id` of a party with no `peppol_id`
  is missing, empty, or only whitespace; `param` names the field. A body that
  is not valid JSON gives `400 invalid_body` (#421).
- For `documents()->sendBatch()`, `param` starts with the index of the item,
  for example `1.to.tax_id` (#421).
- `validate()->document()` answers `valid: false` (and not `500`) for a body
  with no `from` or `to`, with the same checks as a send (#421).

### Fixed

- A sandbox send no longer fails for a party with a `peppol_id`, no `tax_id`
  and no `address.country`. The UBL of a party with no `tax_id` has no
  `PartyTaxScheme` (#425).

## [0.3.0] - 2026-10-16

### Added

- `companies()->delete($id)`: `DELETE /v1/companies/{id}` deletes a company,
  its documents, and its Peppol registration, and returns nothing (the API
  answers `204`). Only workspace owners can delete a company, and the delete
  cannot be undone. A `ps_test_` key can delete only a company that is not
  live; for a live company the API returns `live_company_requires_live_key`
  (#405).

### Changed

- The API no longer sends a `tax_id` with no country prefix (for example the
  Norwegian org number `995313863`) as a VAT number. Give such a party a
  `peppol_id`. A send that has no Peppol ID for the sender or the receiver
  fails with the error code `sender_not_derivable` (#395).

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

[Unreleased]: https://github.com/peppol-sh/peppol-sh-php/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/peppol-sh/peppol-sh-php/releases/tag/v0.4.0
[0.3.0]: https://github.com/peppol-sh/peppol-sh-php/releases/tag/v0.3.0
[0.2.0]: https://github.com/peppol-sh/peppol-sh-php/releases/tag/v0.2.0
[0.1.0]: https://github.com/peppol-sh/peppol-sh-php/releases/tag/v0.1.0
