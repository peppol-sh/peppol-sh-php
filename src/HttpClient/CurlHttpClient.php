<?php

declare(strict_types=1);

namespace PeppolSh\HttpClient;

use PeppolSh\Exception\ConnectionException;
use PeppolSh\Exception\PeppolException;
use PeppolSh\Exception\TimeoutException;

/**
 * The default transport, built on ext-curl. It holds no connection state:
 * each request uses a new handle.
 */
final class CurlHttpClient implements HttpClientInterface
{
    /** The largest time limit in milliseconds: it fits in a 32-bit integer (24 days). */
    private const MAX_TIMEOUT_MS = 2147483647;

    /** @var array<int, mixed> */
    private $curlOptions;

    /**
     * @param array<int, mixed> $curlOptions extra `CURLOPT_*` options (for example a proxy or a CA bundle);
     *                                       they are applied last, so they can replace the defaults
     */
    public function __construct(array $curlOptions = [])
    {
        $this->curlOptions = $curlOptions;
    }

    /**
     * @throws PeppolException when a header has a line break or the time limit is not a number
     */
    public function request(string $method, string $url, array $headers, ?string $body, float $timeout): HttpResponse
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            // A line break in a header starts a new header (header injection).
            if (strpbrk($name . $value, "\r\n") !== false) {
                // The value can be a secret: the message shows only the name.
                throw new PeppolException(sprintf(
                    'The request header "%s" contains a line break',
                    addcslashes($name, "\r\n")
                ));
            }
            $headerLines[] = $name . ': ' . $value;
        }
        if (is_nan($timeout)) {
            throw new PeppolException('The timeout must be a number of seconds');
        }
        // INF and each value too large for the cURL option get the maximum;
        // zero and each negative value get the minimum.
        $milliseconds = ceil($timeout * 1000);
        if ($milliseconds >= self::MAX_TIMEOUT_MS) {
            $timeoutMs = self::MAX_TIMEOUT_MS;
        } else {
            $timeoutMs = $milliseconds < 1 ? 1 : (int) $milliseconds;
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new ConnectionException('Could not initialize cURL');
        }

        // Stops cURL from adding `Expect: 100-continue` to large bodies.
        $headerLines[] = 'Expect:';
        if ($body === null && $method !== 'GET' && $method !== 'HEAD') {
            $headerLines[] = 'Content-Length: 0';
        }

        /** @var array<string, string> $responseHeaders */
        $responseHeaders = [];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_HEADERFUNCTION => static function ($unused, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);
                if (strncmp($trimmed, 'HTTP/', 5) === 0) {
                    // A new status line (for example after `100 Continue`).
                    $responseHeaders = [];
                } elseif ($trimmed !== '' && strpos($trimmed, ':') !== false) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $name = strtolower(trim($name));
                    $value = trim($value);
                    $responseHeaders[$name] = isset($responseHeaders[$name])
                        ? $responseHeaders[$name] . ', ' . $value
                        : $value;
                }

                return strlen($line);
            },
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        foreach ($this->curlOptions as $option => $value) {
            $options[$option] = $value;
        }
        curl_setopt_array($handle, $options);

        $result = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        // No curl_close(): it is deprecated since PHP 8.5. The handle is
        // released when the variable goes away.
        unset($handle);

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new TimeoutException(
                sprintf('Request to %s %s timed out after %ss', $method, $url, (string) $timeout),
                $timeout
            );
        }
        if ($errno !== 0 || !is_string($result)) {
            throw new ConnectionException(
                sprintf('Could not reach %s: %s', self::origin($url), $error !== '' ? $error : 'cURL error ' . $errno)
            );
        }

        return new HttpResponse($status, $responseHeaders, $result);
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
