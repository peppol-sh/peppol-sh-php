<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Exception\PeppolException;

/**
 * Account details, API keys, usage, and the account audit trail.
 *
 * @phpstan-import-type CreateKeyParams from \PeppolSh\Generated\Types
 * @phpstan-import-type CreateKeyResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type GetAccountResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type GetUsageQuery from \PeppolSh\Generated\Types
 * @phpstan-import-type GetUsageResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type ListAccountAuditEventsQuery from \PeppolSh\Generated\Types
 * @phpstan-import-type ListAccountAuditEventsResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type RevokeKeyResponse from \PeppolSh\Generated\Types
 */
class Account extends AbstractResource
{
    /**
     * `GET /v1/account`: the account that owns the API key.
     *
     * @return GetAccountResponse
     *
     * @throws PeppolException
     */
    public function get(): array
    {
        return $this->json('GET', '/v1/account');
    }

    /**
     * `POST /v1/account/keys`: creates an API key. The full key is in the response one time only.
     *
     * @param CreateKeyParams $params
     *
     * @return CreateKeyResponse
     *
     * @throws PeppolException
     */
    public function createKey(array $params = []): array
    {
        return $this->json('POST', '/v1/account/keys', [], $params);
    }

    /**
     * `DELETE /v1/account/keys/{prefix}`: revokes the API key with this prefix.
     *
     * @return RevokeKeyResponse
     *
     * @throws PeppolException
     */
    public function revokeKey(string $prefix): array
    {
        return $this->json('DELETE', '/v1/account/keys/' . self::seg($prefix));
    }

    /**
     * `GET /v1/account/audit`: one page of the account audit trail. Cursor-paginated (`limit`, `cursor`).
     *
     * @param ListAccountAuditEventsQuery $params
     *
     * @return ListAccountAuditEventsResponse
     *
     * @throws PeppolException
     */
    public function listAuditEvents(array $params = []): array
    {
        return $this->json('GET', '/v1/account/audit', $params);
    }

    /**
     * `GET /v1/account/usage`: usage counts for the last `days` days.
     *
     * @param GetUsageQuery $params
     *
     * @return GetUsageResponse
     *
     * @throws PeppolException
     */
    public function getUsage(array $params = []): array
    {
        return $this->json('GET', '/v1/account/usage', $params);
    }
}
