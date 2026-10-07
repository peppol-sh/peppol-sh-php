<?php

declare(strict_types=1);

namespace PeppolSh\Resources;

use PeppolSh\Exception\PeppolException;

/**
 * Webhook endpoints and their deliveries.
 *
 * @phpstan-import-type CreateWebhookParams from \PeppolSh\Generated\Types
 * @phpstan-import-type CreateWebhookResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type DeleteWebhookResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type GetWebhookResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type ListWebhookDeliveriesQuery from \PeppolSh\Generated\Types
 * @phpstan-import-type ListWebhookDeliveriesResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type ListWebhooksResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type RotateWebhookSecretResponse from \PeppolSh\Generated\Types
 * @phpstan-import-type TestWebhookResponse from \PeppolSh\Generated\Types
 */
class Webhooks extends AbstractResource
{
    /**
     * `GET /v1/webhooks`: all webhooks (not paginated).
     *
     * @return ListWebhooksResponse
     *
     * @throws PeppolException
     */
    public function list(): array
    {
        return $this->json('GET', '/v1/webhooks');
    }

    /**
     * `POST /v1/webhooks`: creates a webhook. The signing `secret` is in the response one time only.
     *
     * @param CreateWebhookParams $params
     *
     * @return CreateWebhookResponse
     *
     * @throws PeppolException
     */
    public function create(array $params): array
    {
        return $this->json('POST', '/v1/webhooks', [], $params);
    }

    /**
     * `GET /v1/webhooks/{id}`: one webhook.
     *
     * @return GetWebhookResponse
     *
     * @throws PeppolException
     */
    public function get(string $id): array
    {
        return $this->json('GET', '/v1/webhooks/' . self::seg($id));
    }

    /**
     * `DELETE /v1/webhooks/{id}`: deletes a webhook.
     *
     * @return DeleteWebhookResponse
     *
     * @throws PeppolException
     */
    public function delete(string $id): array
    {
        return $this->json('DELETE', '/v1/webhooks/' . self::seg($id));
    }

    /**
     * `GET /v1/webhooks/{id}/deliveries`: one page of delivery attempts. Cursor-paginated (`limit`, `cursor`).
     *
     * @param ListWebhookDeliveriesQuery $params
     *
     * @return ListWebhookDeliveriesResponse
     *
     * @throws PeppolException
     */
    public function listDeliveries(string $id, array $params = []): array
    {
        return $this->json('GET', '/v1/webhooks/' . self::seg($id) . '/deliveries', $params);
    }

    /**
     * `POST /v1/webhooks/{id}/test`: sends a synthetic `webhook.test` event to the webhook URL now.
     *
     * @return TestWebhookResponse
     *
     * @throws PeppolException
     */
    public function test(string $id): array
    {
        return $this->json('POST', '/v1/webhooks/' . self::seg($id) . '/test');
    }

    /**
     * `POST /v1/webhooks/{id}/rotate-secret`: makes a new signing secret; the old one stays valid for an overlap period.
     *
     * @return RotateWebhookSecretResponse
     *
     * @throws PeppolException
     */
    public function rotateSecret(string $id): array
    {
        return $this->json('POST', '/v1/webhooks/' . self::seg($id) . '/rotate-secret');
    }
}
