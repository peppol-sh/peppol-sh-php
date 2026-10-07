<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Exception\PeppolException;

/**
 * Workspace verification (KYC).
 *
 * @phpstan-import-type GetKycResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type SubmitKycParams from \PeppolSh\Generated\Types
 * @phpstan-import-type SubmitKycResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type UploadKycDocumentParams from \PeppolSh\Generated\Types
 * @phpstan-import-type UploadKycDocumentResponse from \PeppolSh\Generated\Types
 */
class Kyc extends AbstractResource
{
    /**
     * `GET /v1/kyc`: the verification state of the workspace.
     *
     * @return GetKycResponse
     *
     * @throws PeppolException
     */
    public function get(): array
    {
        return $this->json('GET', '/v1/kyc');
    }

    /**
     * `POST /v1/kyc/documents`: uploads one verification document (base64 content in the JSON body).
     *
     * @param UploadKycDocumentParams $params
     *
     * @return UploadKycDocumentResponse
     *
     * @throws PeppolException
     */
    public function uploadDocument(array $params): array
    {
        return $this->json('POST', '/v1/kyc/documents', [], $params);
    }

    /**
     * `POST /v1/kyc/submit`: submits the verification for review.
     *
     * @param SubmitKycParams $params
     *
     * @return SubmitKycResponse
     *
     * @throws PeppolException
     */
    public function submit(array $params): array
    {
        return $this->json('POST', '/v1/kyc/submit', [], $params);
    }
}
