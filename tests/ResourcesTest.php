<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Client;
use PeppolSh\HttpClient\HttpResponse;
use PeppolSh\Testing\FakeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each resource method must send the correct method, path, query, and body.
 */
final class ResourcesTest extends TestCase
{
    /**
     * @dataProvider calls
     *
     * @param callable(Client): mixed   $call
     * @param array<string, mixed>      $query
     * @param array<mixed>|null         $body
     */
    #[DataProvider('calls')]
    public function testMethodSendsTheCorrectRequest(
        callable $call,
        string $method,
        string $path,
        array $query = [],
        ?array $body = null
    ): void {
        $http = new FakeHttpClient([FakeHttpClient::json(['ok' => true])]);
        $client = new Client('ps_test_abc', ['http_client' => $http, 'max_retries' => 0]);

        $result = $call($client);

        self::assertSame(['ok' => true], $result);
        $http->assertRequestCount(1);
        $request = $http->getRequests()[0];
        self::assertSame($method, $request->getMethod());
        self::assertSame('https://api.peppol.sh' . $path, explode('?', $request->getUrl())[0]);
        self::assertSame($query, $request->getQuery());
        self::assertSame($body, $request->getJson());
        self::assertSame('Bearer ps_test_abc', $request->getHeader('authorization'));
        self::assertSame($body === null ? null : 'application/json', $request->getHeader('content-type'));
    }

    /**
     * @return array<string, array{0: callable(Client): mixed, 1: string, 2: string, 3?: array<string, mixed>, 4?: array<mixed>|null}>
     */
    public static function calls(): array
    {
        $doc = Payloads::invoice();
        $kyc = [
            'company_id' => 'com_1',
            'kyc_legal_name' => 'Acme BV',
            'kyc_enterprise_number' => '0123456789',
            'attestation_name' => 'Jan Peeters',
            'attestation_role' => 'Director',
            'attested' => true,
            'attestation_version' => '2026-01',
        ];

        return [
            'account.get' => [fn (Client $c) => $c->account()->get(), 'GET', '/v1/account'],
            'account.createKey' => [
                fn (Client $c) => $c->account()->createKey(['name' => 'ci']),
                'POST', '/v1/account/keys', [], ['name' => 'ci'],
            ],
            'account.revokeKey' => [
                fn (Client $c) => $c->account()->revokeKey('ps_test_ab/cd'),
                'DELETE', '/v1/account/keys/ps_test_ab%2Fcd',
            ],
            'account.listAuditEvents' => [
                fn (Client $c) => $c->account()->listAuditEvents(['limit' => 5, 'cursor' => 'c1']),
                'GET', '/v1/account/audit', ['limit' => '5', 'cursor' => 'c1'],
            ],
            'account.getUsage' => [
                fn (Client $c) => $c->account()->getUsage(['days' => 7]),
                'GET', '/v1/account/usage', ['days' => '7'],
            ],

            'companies.create' => [
                fn (Client $c) => $c->companies()->create(['name' => 'Acme', 'country' => 'BE', 'peppol_id' => '0208:0123456789']),
                'POST', '/v1/companies', [], ['name' => 'Acme', 'country' => 'BE', 'peppol_id' => '0208:0123456789'],
            ],
            'companies.list' => [fn (Client $c) => $c->companies()->list(), 'GET', '/v1/companies'],
            'companies.get' => [fn (Client $c) => $c->companies()->get('com_1'), 'GET', '/v1/companies/com_1'],
            'companies.update' => [
                fn (Client $c) => $c->companies()->update('com_1', ['name' => 'New']),
                'PATCH', '/v1/companies/com_1', [], ['name' => 'New'],
            ],

            'documents.send' => [
                fn (Client $c) => $c->documents()->send($doc + ['idempotency_key' => 'k1']),
                'POST', '/v1/documents', [], $doc + ['idempotency_key' => 'k1'],
            ],
            'documents.sendBatch' => [
                fn (Client $c) => $c->documents()->sendBatch([$doc]),
                'POST', '/v1/documents/batch', [], [$doc],
            ],
            'documents.list' => [
                fn (Client $c) => $c->documents()->list(['company_id' => 'com_1', 'status' => 'delivered']),
                'GET', '/v1/documents', ['company_id' => 'com_1', 'status' => 'delivered'],
            ],
            'documents.get' => [
                fn (Client $c) => $c->documents()->get('doc_1', ['company_id' => 'com_1']),
                'GET', '/v1/documents/doc_1', ['company_id' => 'com_1'],
            ],
            'documents.history' => [
                fn (Client $c) => $c->documents()->history('doc_1'),
                'GET', '/v1/documents/doc_1/history',
            ],
            'documents.listAttachments' => [
                fn (Client $c) => $c->documents()->listAttachments('doc_1'),
                'GET', '/v1/documents/doc_1/attachments',
            ],

            'events.list' => [
                fn (Client $c) => $c->events()->list(['type' => 'delivered', 'limit' => 20]),
                'GET', '/v1/events', ['type' => 'delivered', 'limit' => '20'],
            ],
            'events.list (no params)' => [fn (Client $c) => $c->events()->list(), 'GET', '/v1/events'],

            'kyc.get' => [fn (Client $c) => $c->kyc()->get(), 'GET', '/v1/kyc'],
            'kyc.uploadDocument' => [
                fn (Client $c) => $c->kyc()->uploadDocument(['doc_type' => 'representative_id', 'content' => 'AAAA']),
                'POST', '/v1/kyc/documents', [], ['doc_type' => 'representative_id', 'content' => 'AAAA'],
            ],
            'kyc.submit' => [
                fn (Client $c) => $c->kyc()->submit($kyc),
                'POST', '/v1/kyc/submit', [], $kyc,
            ],

            'lookup.participant' => [
                fn (Client $c) => $c->lookup()->participant('0208:0123456789'),
                'GET', '/v1/lookup/0208%3A0123456789',
            ],
            'lookup.participant (domain)' => [
                fn (Client $c) => $c->lookup()->participant('0208:0123456789', ['domain' => 'smk', 'other' => 'x']),
                'GET', '/v1/lookup/0208%3A0123456789', ['domain' => 'smk'],
            ],
            'lookup.dns' => [
                fn (Client $c) => $c->lookup()->dns('0208:0123456789', ['domain' => 'sml']),
                'GET', '/v1/lookup/0208%3A0123456789/dns', ['domain' => 'sml'],
            ],

            'validate.document' => [
                fn (Client $c) => $c->validate()->document($doc),
                'POST', '/v1/validate', [], $doc,
            ],

            'webhooks.list' => [fn (Client $c) => $c->webhooks()->list(), 'GET', '/v1/webhooks'],
            'webhooks.create' => [
                fn (Client $c) => $c->webhooks()->create(['url' => 'https://example.com/hook', 'events' => ['document.delivered']]),
                'POST', '/v1/webhooks', [], ['url' => 'https://example.com/hook', 'events' => ['document.delivered']],
            ],
            'webhooks.get' => [fn (Client $c) => $c->webhooks()->get('wh_1'), 'GET', '/v1/webhooks/wh_1'],
            'webhooks.delete' => [fn (Client $c) => $c->webhooks()->delete('wh_1'), 'DELETE', '/v1/webhooks/wh_1'],
            'webhooks.listDeliveries' => [
                fn (Client $c) => $c->webhooks()->listDeliveries('wh_1', ['limit' => 2]),
                'GET', '/v1/webhooks/wh_1/deliveries', ['limit' => '2'],
            ],
            'webhooks.test' => [fn (Client $c) => $c->webhooks()->test('wh_1'), 'POST', '/v1/webhooks/wh_1/test'],
            'webhooks.rotateSecret' => [
                fn (Client $c) => $c->webhooks()->rotateSecret('wh_1'),
                'POST', '/v1/webhooks/wh_1/rotate-secret',
            ],

            'workspaces.create' => [
                fn (Client $c) => $c->workspaces()->create(['name' => 'Team']),
                'POST', '/v1/workspaces', [], ['name' => 'Team'],
            ],
            'workspaces.list' => [fn (Client $c) => $c->workspaces()->list(), 'GET', '/v1/workspaces'],
            'workspaces.get' => [fn (Client $c) => $c->workspaces()->get('wsp_1'), 'GET', '/v1/workspaces/wsp_1'],
            'workspaces.update' => [
                fn (Client $c) => $c->workspaces()->update('wsp_1', ['name' => 'New']),
                'PATCH', '/v1/workspaces/wsp_1', [], ['name' => 'New'],
            ],
            'workspaces.delete' => [
                fn (Client $c) => $c->workspaces()->delete('wsp_1'),
                'DELETE', '/v1/workspaces/wsp_1',
            ],
            'workspaces.listMembers' => [
                fn (Client $c) => $c->workspaces()->listMembers('wsp_1'),
                'GET', '/v1/workspaces/wsp_1/members',
            ],
            'workspaces.inviteMember' => [
                fn (Client $c) => $c->workspaces()->inviteMember('wsp_1', ['email' => 'a@b.test', 'role' => 'member']),
                'POST', '/v1/workspaces/wsp_1/members', [], ['email' => 'a@b.test', 'role' => 'member'],
            ],
            'workspaces.changeMemberRole' => [
                fn (Client $c) => $c->workspaces()->changeMemberRole('wsp_1', 'acc_2', ['role' => 'admin']),
                'PATCH', '/v1/workspaces/wsp_1/members/acc_2', [], ['role' => 'admin'],
            ],
            'workspaces.removeMember' => [
                fn (Client $c) => $c->workspaces()->removeMember('wsp_1', 'acc_2'),
                'DELETE', '/v1/workspaces/wsp_1/members/acc_2',
            ],
            'workspaces.transferOwnership' => [
                fn (Client $c) => $c->workspaces()->transferOwnership('wsp_1', ['account_id' => 'acc_2']),
                'POST', '/v1/workspaces/wsp_1/transfer-ownership', [], ['account_id' => 'acc_2'],
            ],
            'workspaces.listAuditEvents' => [
                fn (Client $c) => $c->workspaces()->listAuditEvents('wsp_1', ['cursor' => 'c2']),
                'GET', '/v1/workspaces/wsp_1/audit', ['cursor' => 'c2'],
            ],
        ];
    }

    public function testSendPassesThePrecedingInvoiceReferenceInTheBody(): void
    {
        $preceding = ['number' => 'INV-2026-001', 'issue_date' => '2026-03-01'];
        $creditNote = ['type' => 'credit_note', 'preceding_invoice' => $preceding] + Payloads::invoice();
        $http = new FakeHttpClient([FakeHttpClient::json(['id' => 'doc_1', 'status' => 'queued'], 202)]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $client->documents()->send($creditNote);

        $http->assertSent('POST', '/v1/documents');
        $json = $http->getRequests()[0]->getJson();
        self::assertIsArray($json);
        self::assertSame('credit_note', $json['type']);
        self::assertSame($preceding, $json['preceding_invoice']);
    }

    public function testGetReturnsThePrecedingInvoiceReference(): void
    {
        $preceding = ['number' => 'INV-2026-001', 'issue_date' => '2026-03-01'];
        $http = new FakeHttpClient([
            FakeHttpClient::json(['id' => 'doc_1', 'type' => 'credit_note', 'preceding_invoice' => $preceding]),
        ]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $document = $client->documents()->get('doc_1', ['company_id' => 'com_1']);

        self::assertSame($preceding, $document['preceding_invoice'] ?? null);
    }

    public function testSendPassesTheAmountDueInTheBody(): void
    {
        $prepaid = ['amount_due' => 0] + Payloads::invoice();
        $http = new FakeHttpClient([FakeHttpClient::json(['id' => 'doc_1', 'status' => 'queued'], 202)]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $client->documents()->send($prepaid);

        $http->assertSent('POST', '/v1/documents');
        $json = $http->getRequests()[0]->getJson();
        self::assertIsArray($json);
        self::assertSame(0, $json['amount_due']);
    }

    public function testGetReturnsTheAmountDue(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::json(['id' => 'doc_1', 'type' => 'invoice', 'total' => 121, 'amount_due' => 0]),
        ]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $document = $client->documents()->get('doc_1', ['company_id' => 'com_1']);

        self::assertSame(0, $document['amount_due'] ?? null);
    }

    public function testUblReturnsTheXmlString(): void
    {
        $xml = '<?xml version="1.0"?><Invoice/>';
        $http = new FakeHttpClient([new HttpResponse(200, ['content-type' => 'application/xml'], $xml)]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        self::assertSame($xml, $client->documents()->ubl('doc_1'));
        $http->assertSent('GET', '/v1/documents/doc_1/ubl');
    }

    public function testGetAttachmentReturnsTheBytes(): void
    {
        $bytes = "%PDF-1.7\x00\xFF\xFE binary";
        $http = new FakeHttpClient([new HttpResponse(200, ['content-type' => 'application/octet-stream'], $bytes)]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        self::assertSame($bytes, $client->documents()->getAttachment('doc_1', 'att 1'));
        $http->assertSent('GET', '/v1/documents/doc_1/attachments/att%201');
    }

    public function testEmptyResponseBodyGivesAnEmptyArray(): void
    {
        $http = new FakeHttpClient([new HttpResponse(204)]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        self::assertSame([], $client->workspaces()->removeMember('wsp_1', 'acc_2'));
    }
}
