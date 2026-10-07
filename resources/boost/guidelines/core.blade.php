## peppol.sh SDK (peppol-sh/sdk)

- This package is the official PHP SDK for the peppol.sh Peppol API. It sends Peppol e-invoicing documents (invoices and credit notes) from JSON. Do not build UBL XML by hand: send the JSON payload and the API creates the UBL.
- The package has a Laravel integration: the `Peppol` facade, the `PeppolSh\Client` singleton, the `peppol.webhook` route middleware, and `Peppol::fake()` for tests. Laravel finds the service provider automatically.
- Full API reference: https://peppol.sh/docs

### Setup

- Install with `composer require peppol-sh/sdk`.
- Set these keys in `.env`. Never write an API key in the code.

@verbatim
<code-snippet name=".env keys for the peppol.sh SDK" lang="ini">
PEPPOL_API_KEY=ps_test_...
PEPPOL_BASE_URL=https://sandbox.peppol.sh
PEPPOL_WEBHOOK_SECRET=
</code-snippet>
@endverbatim

- A sandbox key starts with `ps_test_` and needs `PEPPOL_BASE_URL=https://sandbox.peppol.sh`. A production key starts with `ps_live_` and uses the default `https://api.peppol.sh`. The SDK does NOT select the URL from the key prefix.
- Optional keys: `PEPPOL_TIMEOUT` (seconds, default 30), `PEPPOL_MAX_RETRIES` (default 2), `PEPPOL_WEBHOOK_TOLERANCE` (seconds, default 300).
- To change the config file, publish it with `php artisan vendor:publish --tag=peppol-config`. Read values with `config('peppol.api_key')`, not with `env()`.

### Send a document

- Use the `PeppolSh\Laravel\Facades\Peppol` facade, or inject `PeppolSh\Client`. Both give the same singleton.
- All methods take and return plain arrays with the snake_case field names of the API.
- `company_id` is the id (`com_...`) of the sending company in the peppol.sh workspace. Get it from `Peppol::companies()->list()` or create one with `Peppol::companies()->create([...])`.
- Always set `idempotency_key` to a stable value for each document (for example the invoice number). A repeated call with the same key returns the first document and does not send a duplicate.
- Sending is asynchronous. `send()` returns `id`, `status` (`queued`) and `url`. Use a webhook, or `Peppol::documents()->get($id, ['company_id' => $companyId])`, to get the final status.

@verbatim
<code-snippet name="Send an invoice" lang="php">
use PeppolSh\Laravel\Facades\Peppol;

$document = Peppol::documents()->send([
    'company_id' => $companyId,
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
            'unit' => 'C62',
            'unit_price' => 500.0,
            'tax_rate' => 21.0,
        ],
    ],
    'payment_means' => ['method' => 'bank_transfer', 'iban' => 'BE68539007547034'],
    'idempotency_key' => 'acme-INV-2026-001',
]);

$document['id'];     // "doc_..."
$document['status']; // "queued"
</code-snippet>
@endverbatim

- `unit` is a UN/CEFACT unit code (`C62` is "piece"). `peppol_id` has the format `<scheme>:<value>` (`0208` is the Belgian enterprise number).
- A credit note has `'type' => 'credit_note'`. To add the preceding invoice reference (the invoice that the credit note credits), set `preceding_invoice`: `['number' => 'INV-2026-001', 'issue_date' => '2026-03-01']`. `number` is mandatory, `issue_date` is optional.
- For a document that is paid before you send it, set `amount_due` to the amount that is still to be paid: `'amount_due' => 0` for a fully prepaid document. The value must be from `0` up to the document total with VAT. The prepaid amount is not an input: it is the total less `amount_due`.
- Dependency injection gives the same client:

@verbatim
<code-snippet name="Inject the client" lang="php">
use PeppolSh\Client;

final class SendInvoice
{
    /** @var Client */
    private $peppol;

    public function __construct(Client $peppol)
    {
        $this->peppol = $peppol;
    }

    public function handle(array $payload): array
    {
        return $this->peppol->documents()->send($payload);
    }
}
</code-snippet>
@endverbatim

### Pagination

- A list method returns one page: `data`, `has_more`, `next_cursor`. Use `PeppolSh\Pagination::iterate` to go through all pages; it fetches a page only when the loop gets to it.

@verbatim
<code-snippet name="Iterate all documents of a company" lang="php">
use PeppolSh\Laravel\Facades\Peppol;
use PeppolSh\Pagination;

$documents = Pagination::iterate([Peppol::documents(), 'list'], ['company_id' => $companyId]);

foreach ($documents as $document) {
    // $document is an array
}
</code-snippet>
@endverbatim

### Errors

- Every SDK exception extends `PeppolSh\Exception\PeppolException`.
- `PeppolSh\Exception\ApiException` is an error response from the API. It has `getStatusCode()`, `getErrorCode()`, `getErrorType()`, `getParam()`, `getDetails()` and `getRequestId()`.
- Subclasses of `ApiException` in `PeppolSh\Exception`: `ValidationException` (400, 422), `AuthenticationException` (401), `PermissionException` (403), `NotFoundException` (404), `ConflictException` (409), `RateLimitException` (429, with `getRetryAfter()`), `ServerException` (5xx).
- `ConnectionException` and `TimeoutException` mean that the request did not complete. A timed-out send is safe to do again only with the same `idempotency_key`.
- The client retries HTTP 429, and connection errors and HTTP 5xx on a GET. Do not add a second retry loop.

@verbatim
<code-snippet name="Handle API errors" lang="php">
use PeppolSh\Exception\ApiException;
use PeppolSh\Exception\ValidationException;
use PeppolSh\Laravel\Facades\Peppol;

try {
    Peppol::documents()->send($payload);
} catch (ValidationException $e) {
    report($e); // $e->getErrorCode(), $e->getParam(), $e->getDetails()
} catch (ApiException $e) {
    report($e); // $e->getStatusCode(), $e->getRequestId()
}
</code-snippet>
@endverbatim

### Webhooks

- Put the `peppol.webhook` middleware on the route that receives peppol.sh webhooks. It verifies the `X-Peppol-Signature-V2` header against the raw body with `PEPPOL_WEBHOOK_SECRET`. A bad signature gets HTTP 400. Do not write a second signature check.
- Put the route in `routes/api.php`. If the route is in the `web` group, exclude it from CSRF verification.
- peppol.sh can deliver an event more than one time. Use the `X-Peppol-Delivery-Id` header to ignore a delivery that was already processed. Return a 2xx status quickly and do slow work in a queued job.

@verbatim
<code-snippet name="Webhook route" lang="php">
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/peppol', function (Request $request) {
    $event = $request->json()->all();
    $deliveryId = $request->header('X-Peppol-Delivery-Id');

    // ... dispatch a job with $event ...

    return response()->noContent();
})->middleware('peppol.webhook');
</code-snippet>
@endverbatim

- Outside a route, verify a delivery with `PeppolSh\Webhook::verify($rawBody, $signatureHeader, $secret)`. It returns the event array or throws `PeppolSh\Exception\SignatureVerificationException`.

### Tests

- Call `Peppol::fake([...])` at the start of a test. No HTTP request leaves the application and no API key is necessary. It returns a `PeppolSh\Testing\FakeHttpClient`; the facade and each injected `PeppolSh\Client` use it.
- Queue one response for each request the code makes, in order. A request with an empty queue throws a `LogicException`.
- Build a signed webhook request in a test with `PeppolSh\Webhook::signatureHeader($rawBody, $secret)`.

@verbatim
<code-snippet name="Test code that sends a document" lang="php">
use PeppolSh\Laravel\Facades\Peppol;
use PeppolSh\Testing\FakeHttpClient;

$http = Peppol::fake([
    FakeHttpClient::json(['id' => 'doc_1', 'status' => 'queued', 'url' => '/v1/documents/doc_1'], 202),
]);

// ... run the code that calls Peppol::documents()->send([...]) ...

$http->assertSent('POST', '/v1/documents');
$http->assertRequestCount(1);

// An API error response:
Peppol::fake([FakeHttpClient::error(422, 'validation_failed', 'The document is not valid')]);
</code-snippet>
@endverbatim
