<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Exception\PeppolException;

/**
 * The event log.
 *
 * @phpstan-import-type ListEventsQuery from \PeppolSh\Generated\Types
 * @phpstan-import-type ListEventsResponse from \PeppolSh\Generated\Types
 */
class Events extends AbstractResource
{
    /**
     * `GET /v1/events`: one page of events. Cursor-paginated (`company_id`, `type`, `from`, `to`, `document_id`, `q`, `limit`, `cursor`).
     *
     * @param ListEventsQuery $params
     *
     * @return ListEventsResponse
     *
     * @throws PeppolException
     */
    public function list(array $params = []): array
    {
        return $this->json('GET', '/v1/events', $params);
    }
}
