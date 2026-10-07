<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Exception\PeppolException;

/**
 * Peppol participant lookup (SMP and DNS).
 *
 * @phpstan-import-type LookupParticipantDnsQuery from \PeppolSh\Generated\Types
 * @phpstan-import-type LookupParticipantDnsResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type LookupParticipantQuery from \PeppolSh\Generated\Types
 * @phpstan-import-type LookupParticipantResponse from \PeppolSh\Generated\Types
 */
class Lookup extends AbstractResource
{
    /**
     * `GET /v1/lookup/{peppol_id}`: SMP lookup of a participant, for example `0208:0123456789`.
     *
     * @param LookupParticipantQuery $params optional `domain`: the SML domain to query
     *
     * @return LookupParticipantResponse
     *
     * @throws PeppolException
     */
    public function participant(string $peppolId, array $params = []): array
    {
        return $this->json('GET', '/v1/lookup/' . self::seg($peppolId), ['domain' => $params['domain'] ?? null]);
    }

    /**
     * `GET /v1/lookup/{peppol_id}/dns`: the SML DNS records of a participant.
     *
     * @param LookupParticipantDnsQuery $params optional `domain`: the SML domain to query
     *
     * @return LookupParticipantDnsResponse
     *
     * @throws PeppolException
     */
    public function dns(string $peppolId, array $params = []): array
    {
        return $this->json('GET', '/v1/lookup/' . self::seg($peppolId) . '/dns', ['domain' => $params['domain'] ?? null]);
    }
}
