<?php

declare(strict_types=1);

namespace PeppolSh\Laravel\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PeppolSh\Exception\PeppolException;
use PeppolSh\Exception\SignatureVerificationException;
use PeppolSh\Webhook;

/**
 * Route middleware (alias `peppol.webhook`) that verifies the
 * `X-Peppol-Signature-V2` header of a peppol.sh webhook delivery.
 *
 *     Route::post('/webhooks/peppol', PeppolWebhookController::class)->middleware('peppol.webhook');
 *
 * It signs the RAW request body with `config('peppol.webhook_secret')` and
 * applies `config('peppol.webhook_tolerance')` seconds to the timestamp.
 *
 * - Valid signature: the request continues. Read the event with
 *   `$request->json()->all()`.
 * - Invalid or missing signature, or a timestamp out of tolerance: HTTP 400
 *   with a generic JSON error. The response does not give the reason.
 * - No secret in the config: a `PeppolException` (HTTP 500), because that is
 *   a server configuration error and not a bad request.
 *
 * The middleware works in the `api` and the `web` middleware groups. peppol.sh
 * cannot send a CSRF token, so a route in the `web` group must be excluded
 * from CSRF verification (`$except` of `VerifyCsrfToken` up to Laravel 10,
 * `$middleware->validateCsrfTokens(except: [...])` from Laravel 11). A route
 * in `routes/api.php` needs no exclusion.
 */
class VerifyWebhookSignature
{
    /** @var Repository */
    private $config;

    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed the response of the next middleware, or the HTTP 400 response
     *
     * @throws PeppolException when `peppol.webhook_secret` is not configured
     */
    public function handle($request, Closure $next)
    {
        $secret = $this->config->get('peppol.webhook_secret');
        if (!is_string($secret) || $secret === '') {
            throw new PeppolException(
                'The peppol.sh webhook secret is not configured. Set PEPPOL_WEBHOOK_SECRET in your .env file'
                . ' (config key "peppol.webhook_secret").'
            );
        }

        $header = $request->headers->get(Webhook::SIGNATURE_HEADER);
        $body = $request->getContent();

        try {
            Webhook::verifySignature(
                is_string($body) ? $body : '',
                is_string($header) ? $header : '',
                $secret,
                $this->tolerance()
            );
        } catch (SignatureVerificationException $e) {
            return new JsonResponse([
                'error' => [
                    'type' => 'invalid_request',
                    'code' => 'invalid_signature',
                    'message' => 'The webhook signature is not valid.',
                ],
            ], 400);
        }

        return $next($request);
    }

    private function tolerance(): int
    {
        $tolerance = $this->config->get('peppol.webhook_tolerance');
        if (is_string($tolerance) && preg_match('/\A\d+\z/', $tolerance) === 1) {
            $tolerance = (int) $tolerance;
        }

        return is_int($tolerance) && $tolerance >= 0 ? $tolerance : Webhook::DEFAULT_TOLERANCE;
    }
}
