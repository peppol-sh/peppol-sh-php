<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Client;
use PHPUnit\Framework\TestCase;

/**
 * Guards against drift from the API: each `operationId` in the OpenAPI spec
 * must be mapped to an SDK method or be excluded with a reason. The spec is
 * not in the mirrored public repository, so the spec tests skip there.
 */
final class RouteCoverageTest extends TestCase
{
    /**
     * operationId => "resource.method", or "client.method" for the root methods.
     */
    private const MAP = [
        'getHealth' => 'client.health',
        'signup' => 'client.signup',

        'getAccount' => 'account.get',
        'createKey' => 'account.createKey',
        'revokeKey' => 'account.revokeKey',
        'listAccountAuditEvents' => 'account.listAuditEvents',
        'getUsage' => 'account.getUsage',

        'createCompany' => 'companies.create',
        'listCompanies' => 'companies.list',
        'getCompany' => 'companies.get',
        'updateCompany' => 'companies.update',

        'sendDocument' => 'documents.send',
        'sendDocumentBatch' => 'documents.sendBatch',
        'listDocuments' => 'documents.list',
        'getDocument' => 'documents.get',
        'getDocumentHistory' => 'documents.history',
        'getDocumentUbl' => 'documents.ubl',
        'listDocumentAttachments' => 'documents.listAttachments',
        'getDocumentAttachment' => 'documents.getAttachment',

        'listEvents' => 'events.list',

        'getKyc' => 'kyc.get',
        'uploadKycDocument' => 'kyc.uploadDocument',
        'submitKyc' => 'kyc.submit',

        'lookupParticipant' => 'lookup.participant',
        'lookupParticipantDns' => 'lookup.dns',

        'validateDocument' => 'validate.document',

        'listWebhooks' => 'webhooks.list',
        'createWebhook' => 'webhooks.create',
        'getWebhook' => 'webhooks.get',
        'deleteWebhook' => 'webhooks.delete',
        'listWebhookDeliveries' => 'webhooks.listDeliveries',
        'testWebhook' => 'webhooks.test',
        'rotateWebhookSecret' => 'webhooks.rotateSecret',

        'createWorkspace' => 'workspaces.create',
        'listWorkspaces' => 'workspaces.list',
        'getWorkspace' => 'workspaces.get',
        'updateWorkspace' => 'workspaces.update',
        'deleteWorkspace' => 'workspaces.delete',
        'listWorkspaceMembers' => 'workspaces.listMembers',
        'inviteWorkspaceMember' => 'workspaces.inviteMember',
        'changeWorkspaceMemberRole' => 'workspaces.changeMemberRole',
        'removeWorkspaceMember' => 'workspaces.removeMember',
        'transferWorkspaceOwnership' => 'workspaces.transferOwnership',
        'listWorkspaceAuditEvents' => 'workspaces.listAuditEvents',
    ];

    /**
     * operationId => the reason the SDK does not cover it.
     */
    private const EXCLUDED = [
        'getProtectedResourceMetadata' => 'OAuth discovery document for MCP clients; not an API call. The TypeScript SDK has no method for it.',
    ];

    public function testEachMappedMethodExists(): void
    {
        $client = new Client('ps_test_abc');

        foreach (self::MAP as $operationId => $target) {
            [$resource, $method] = explode('.', $target);
            $object = $client;
            if ($resource !== 'client') {
                self::assertTrue(method_exists($client, $resource), "No Client::$resource() for $operationId");
                $object = $client->{$resource}();
            }
            self::assertIsObject($object);
            self::assertTrue(
                is_callable([$object, $method]),
                sprintf('%s maps to %s, but that method does not exist', $operationId, $target)
            );
        }
    }

    public function testEachSdkTargetIsMappedOneTime(): void
    {
        self::assertSame(
            array_values(array_unique(self::MAP)),
            array_values(self::MAP),
            'Two operations map to the same SDK method.'
        );
        self::assertSame([], array_intersect_key(self::MAP, self::EXCLUDED), 'An operation is mapped and excluded.');
    }

    public function testEachPublicResourceMethodIsInTheMap(): void
    {
        $client = new Client('ps_test_abc');
        $targets = array_flip(self::MAP);

        foreach (['account', 'companies', 'documents', 'events', 'kyc', 'lookup', 'validate', 'webhooks', 'workspaces'] as $resource) {
            $object = $client->{$resource}();
            foreach ((new \ReflectionClass($object))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->isStatic()) {
                    continue;
                }
                $target = $resource . '.' . $method->getName();
                self::assertArrayHasKey($target, $targets, "$target has no operationId in the map");
            }
        }
    }

    public function testEachSpecOperationIsMappedOrExcluded(): void
    {
        $spec = dirname(__DIR__, 3) . '/apps/api/src/openapi.yaml';
        if (!is_file($spec)) {
            self::markTestSkipped('No OpenAPI spec next to the package (mirrored repository).');
        }

        preg_match_all('/^\s+operationId:\s*["\']?([A-Za-z0-9_]+)["\']?\s*$/m', (string) file_get_contents($spec), $matches);
        $operationIds = $matches[1];
        self::assertNotEmpty($operationIds, 'No operationId found: the parser or the spec layout changed.');
        self::assertSame(array_values(array_unique($operationIds)), $operationIds, 'Duplicate operationId in the spec.');

        $known = array_merge(array_keys(self::MAP), array_keys(self::EXCLUDED));

        self::assertSame(
            [],
            array_values(array_diff($operationIds, $known)),
            'The spec has operations the SDK does not cover. Add a method and map it, or exclude it with a reason.'
        );
        self::assertSame(
            [],
            array_values(array_diff($known, $operationIds)),
            'The map names operations that are not in the spec any more.'
        );
    }
}
