<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Client;
use PeppolSh\Exception\PeppolException;

/**
 * Base for the resource namespaces. A resource holds only the client and
 * sends every call through it.
 */
abstract class AbstractResource
{
    /** @var Client */
    private $client;

    final public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Sends a request and returns the decoded JSON object (an empty array
     * when the response has no body).
     *
     * The SDK does not validate a response body. Each resource method documents
     * the shape that the OpenAPI spec gives for its endpoint (the aliases in
     * `\PeppolSh\Generated\Types`), and PHPStan cannot prove that shape from
     * decoded JSON: this is the reason for the PHPStan ignore comments
     * (`return.type`) on the `return` lines of the resources.
     *
     * @param array<string, mixed> $query
     * @param array<mixed>|null    $body  a JSON object, or a list for an endpoint that takes a JSON array
     * @param bool                 $list  true for an endpoint that takes a JSON array: the body is sent as a
     *                                    JSON array, also when it is empty or its keys are not `0..n`
     *
     * @return array<string, mixed>
     *
     * @throws PeppolException
     */
    protected function json(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $list = false
    ): array {
        $result = $this->client->request($method, $path, $query, $body, true, $list);

        return is_array($result) ? $result : [];
    }

    /**
     * Sends a request and returns the response body as received.
     *
     * @throws PeppolException
     */
    protected function raw(string $method, string $path): string
    {
        return $this->client->requestRaw($method, $path)->getBody();
    }

    /**
     * Encodes one path segment.
     */
    protected static function seg(string $value): string
    {
        return rawurlencode($value);
    }
}
