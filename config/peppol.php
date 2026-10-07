<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API key
    |--------------------------------------------------------------------------
    |
    | `ps_test_...` is a sandbox key, `ps_live_...` is a production key. The
    | SDK throws a PeppolSh\Exception\PeppolException the first time the
    | client is used when this value is empty.
    |
    */

    'api_key' => env('PEPPOL_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The client does NOT select the environment from the key prefix. A
    | sandbox key (`ps_test_`) needs PEPPOL_BASE_URL=https://sandbox.peppol.sh.
    | A production key (`ps_live_`) uses the default.
    |
    */

    'base_url' => env('PEPPOL_BASE_URL', 'https://api.peppol.sh'),

    /*
    |--------------------------------------------------------------------------
    | Timeout and retries
    |--------------------------------------------------------------------------
    |
    | `timeout` is the time limit for each attempt, in seconds. `max_retries`
    | is the number of extra attempts after a failure that can be retried
    | (HTTP 429, and connection errors or HTTP 5xx on a GET).
    |
    */

    'timeout' => env('PEPPOL_TIMEOUT', 30),

    'max_retries' => env('PEPPOL_MAX_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | The `peppol.webhook` route middleware verifies the
    | `X-Peppol-Signature-V2` header with this signing secret. The API shows
    | the secret one time, when you create the webhook or rotate its secret.
    | `webhook_tolerance` is the permitted difference between the signed time
    | and the server clock, in seconds; 0 disables the time check.
    |
    */

    'webhook_secret' => env('PEPPOL_WEBHOOK_SECRET'),

    'webhook_tolerance' => env('PEPPOL_WEBHOOK_TOLERANCE', 300),

];
