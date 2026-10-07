<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Exception\PeppolException;

/**
 * Validate a document without sending it.
 *
 * @phpstan-import-type ValidateDocumentParams from \PeppolSh\Generated\Types
 * @phpstan-import-type ValidateDocumentResponse from \PeppolSh\Generated\Types
 */
class Validate extends AbstractResource
{
    /**
     * `POST /v1/validate`: validates a document and returns the rule results. Nothing is sent.
     *
     * @param ValidateDocumentParams $params
     *
     * @return ValidateDocumentResponse
     *
     * @throws PeppolException
     */
    public function document(array $params): array
    {
        return $this->json('POST', '/v1/validate', [], $params);
    }
}
