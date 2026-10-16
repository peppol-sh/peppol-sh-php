<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Exception\PeppolException;

/**
 * The companies (legal entities) you send for.
 *
 * @phpstan-import-type CreateCompanyParams from \PeppolSh\Generated\Types
 * @phpstan-import-type CreateCompanyResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type GetCompanyResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type ListCompaniesResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type UpdateCompanyParams from \PeppolSh\Generated\Types
 * @phpstan-import-type UpdateCompanyResponse from \PeppolSh\Generated\Types
 */
class Companies extends AbstractResource
{
    /**
     * `POST /v1/companies`: creates a company.
     *
     * @param CreateCompanyParams $params
     *
     * @return CreateCompanyResponse
     *
     * @throws PeppolException
     */
    public function create(array $params): array
    {
        return $this->json('POST', '/v1/companies', [], $params);
    }

    /**
     * `GET /v1/companies`: all companies of the workspace (not paginated).
     *
     * @return ListCompaniesResponse
     *
     * @throws PeppolException
     */
    public function list(): array
    {
        return $this->json('GET', '/v1/companies');
    }

    /**
     * `GET /v1/companies/{id}`: one company.
     *
     * @return GetCompanyResponse
     *
     * @throws PeppolException
     */
    public function get(string $id): array
    {
        return $this->json('GET', '/v1/companies/' . self::seg($id));
    }

    /**
     * `PATCH /v1/companies/{id}`: changes the given fields of a company.
     *
     * @param UpdateCompanyParams $params
     *
     * @return UpdateCompanyResponse
     *
     * @throws PeppolException
     */
    public function update(string $id, array $params): array
    {
        return $this->json('PATCH', '/v1/companies/' . self::seg($id), [], $params);
    }

    /**
     * `DELETE /v1/companies/{id}`: deletes a company, its documents, and its
     * Peppol registration. Owners only; it cannot be undone. A `ps_test_` key
     * can delete only a company that is not live.
     *
     * @throws PeppolException
     */
    public function delete(string $id): void
    {
        $this->json('DELETE', '/v1/companies/' . self::seg($id));
    }
}
