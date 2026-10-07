<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Exception\PeppolException;

/**
 * Send documents and read their status, history, UBL, and attachments.
 *
 * @phpstan-import-type GetDocumentHistoryResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type GetDocumentQuery from \PeppolSh\Generated\Types
 * @phpstan-import-type GetDocumentResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type ListDocumentAttachmentsResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type ListDocumentsQuery from \PeppolSh\Generated\Types
 * @phpstan-import-type ListDocumentsResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type SendDocumentBatchParams from \PeppolSh\Generated\Types
 * @phpstan-import-type SendDocumentBatchResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type SendDocumentParams from \PeppolSh\Generated\Types
 * @phpstan-import-type SendDocumentResponse from \PeppolSh\Generated\Types
 */
class Documents extends AbstractResource
{
    /**
     * `POST /v1/documents`: sends one document. Put `idempotency_key` in the params to make a retry safe.
     *
     * @param SendDocumentParams $params
     *
     * @return SendDocumentResponse
     *
     * @throws PeppolException
     */
    public function send(array $params): array
    {
        return $this->json('POST', '/v1/documents', [], $params);
    }

    /**
     * `POST /v1/documents/batch`: sends more than one document in one call.
     * The body is always a JSON array: the array keys of `$params` are not sent.
     *
     * @param SendDocumentBatchParams $params
     *
     * @return SendDocumentBatchResponse
     *
     * @throws PeppolException
     */
    public function sendBatch(array $params): array
    {
        return $this->json('POST', '/v1/documents/batch', [], $params, true);
    }

    /**
     * `GET /v1/documents`: one page of documents of a company. Cursor-paginated (`company_id`, `status`, `limit`, `cursor`).
     *
     * @param ListDocumentsQuery $params
     *
     * @return ListDocumentsResponse
     *
     * @throws PeppolException
     */
    public function list(array $params): array
    {
        return $this->json('GET', '/v1/documents', $params);
    }

    /**
     * `GET /v1/documents/{id}`: one document. `company_id` is necessary in the params.
     *
     * @param GetDocumentQuery $params
     *
     * @return GetDocumentResponse
     *
     * @throws PeppolException
     */
    public function get(string $id, array $params): array
    {
        return $this->json('GET', '/v1/documents/' . self::seg($id), $params);
    }

    /**
     * `GET /v1/documents/{id}/history`: the status history of a document.
     *
     * @return GetDocumentHistoryResponse
     *
     * @throws PeppolException
     */
    public function history(string $id): array
    {
        return $this->json('GET', '/v1/documents/' . self::seg($id) . '/history');
    }

    /**
     * `GET /v1/documents/{id}/ubl`: the UBL XML of a document, as a string.
     *
     * @throws PeppolException
     */
    public function ubl(string $id): string
    {
        return $this->raw('GET', '/v1/documents/' . self::seg($id) . '/ubl');
    }

    /**
     * `GET /v1/documents/{id}/attachments`: the attachments of a document.
     *
     * @return ListDocumentAttachmentsResponse
     *
     * @throws PeppolException
     */
    public function listAttachments(string $id): array
    {
        return $this->json('GET', '/v1/documents/' . self::seg($id) . '/attachments');
    }

    /**
     * `GET /v1/documents/{id}/attachments/{att_id}`: the bytes of one attachment, as a binary string.
     *
     * @throws PeppolException
     */
    public function getAttachment(string $id, string $attachmentId): string
    {
        return $this->raw('GET', '/v1/documents/' . self::seg($id) . '/attachments/' . self::seg($attachmentId));
    }
}
