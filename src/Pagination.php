<?php

declare(strict_types=1);

namespace PeppolSh;

/**
 * Walks every item of a cursor-paginated list endpoint.
 *
 * Each list method returns one raw page: `['data' => [...], 'has_more' => bool,
 * 'next_cursor' => string|null]`. `Pagination::iterate()` calls the list
 * method again and again with the `cursor` of the previous page and yields
 * the items one at a time. A page is fetched only when the loop gets to it.
 *
 *     $items = Pagination::iterate([$peppol->documents(), 'list'], ['company_id' => 'com_123']);
 *     foreach ($items as $document) { ... }
 *
 *     // A list method that takes an id first:
 *     Pagination::iterate(fn (array $p) => $peppol->webhooks()->listDeliveries('wh_123', $p));
 *
 * Cursor-paginated endpoints: `documents()->list`, `events()->list`,
 * `account()->listAuditEvents`, `workspaces()->listAuditEvents`,
 * `webhooks()->listDeliveries`.
 */
final class Pagination
{
    private function __construct()
    {
    }

    /**
     * @template TParams of array<string, mixed>
     *
     * @param callable(TParams): array<string, mixed> $fetchPage receives the query params, returns one page
     * @param TParams                                 $params    params for the first page (filters, `limit`, optional `cursor`)
     *
     * @return \Generator<int, mixed, mixed, void> the items of every page, in order
     */
    public static function iterate(callable $fetchPage, array $params = []): \Generator
    {
        while (true) {
            $page = $fetchPage($params); // @phpstan-ignore argument.type (with the added `cursor` key, PHPStan cannot prove the array is still TParams)

            $items = $page['data'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    yield $item;
                }
            }

            $cursor = $page['next_cursor'] ?? null;
            $hasMore = $page['has_more'] ?? true;
            if ($hasMore !== true || (!is_string($cursor) && !is_int($cursor)) || $cursor === '') {
                return;
            }
            if (($params['cursor'] ?? null) === $cursor) {
                // The API gave the same cursor again: stop, do not loop forever.
                return;
            }
            $params['cursor'] = $cursor;
        }
    }
}
