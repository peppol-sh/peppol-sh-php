<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Client;
use PeppolSh\Pagination;
use PeppolSh\Testing\FakeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function testIteratesAllPagesLazily(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::json(['data' => [['id' => 'doc_1'], ['id' => 'doc_2']], 'has_more' => true, 'next_cursor' => 'c1']),
            FakeHttpClient::json(['data' => [['id' => 'doc_3']], 'has_more' => true, 'next_cursor' => 'c2']),
            FakeHttpClient::json(['data' => [['id' => 'doc_4']], 'has_more' => false, 'next_cursor' => null]),
        ]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $items = Pagination::iterate([$client->documents(), 'list'], ['company_id' => 'com_1', 'limit' => 2]);

        // A generator: no request before the first item is read.
        $http->assertNothingSent();

        $ids = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            $ids[] = $item['id'];
            if (count($ids) === 1) {
                $http->assertRequestCount(1);
            }
        }

        self::assertSame(['doc_1', 'doc_2', 'doc_3', 'doc_4'], $ids);
        $http->assertRequestCount(3);
        $requests = $http->getRequests();
        self::assertSame(['company_id' => 'com_1', 'limit' => '2'], $requests[0]->getQuery());
        self::assertSame(['company_id' => 'com_1', 'limit' => '2', 'cursor' => 'c1'], $requests[1]->getQuery());
        self::assertSame(['company_id' => 'com_1', 'limit' => '2', 'cursor' => 'c2'], $requests[2]->getQuery());
    }

    public function testWorksWithAClosureForMethodsThatTakeAnId(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::json(['data' => [['id' => 'del_1']], 'has_more' => true, 'next_cursor' => 'del_1']),
            FakeHttpClient::json(['data' => [['id' => 'del_2']], 'has_more' => false, 'next_cursor' => null]),
        ]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $items = Pagination::iterate(fn (array $params): array => $client->webhooks()->listDeliveries('wh_1', $params));

        self::assertSame([['id' => 'del_1'], ['id' => 'del_2']], iterator_to_array($items, false));
        self::assertSame('/v1/webhooks/wh_1/deliveries', $http->getRequests()[1]->getPath());
        self::assertSame(['cursor' => 'del_1'], $http->getRequests()[1]->getQuery());
    }

    public function testStopsEarlyWhenTheCallerBreaks(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::json(['data' => [['id' => 'evt_1']], 'has_more' => true, 'next_cursor' => 'c1']),
        ]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        foreach (Pagination::iterate([$client->events(), 'list']) as $event) {
            self::assertSame(['id' => 'evt_1'], $event);
            break;
        }

        $http->assertRequestCount(1);
    }

    /**
     * @dataProvider lastPages
     *
     * @param array<string, mixed> $page
     */
    #[DataProvider('lastPages')]
    public function testStopsOnTheLastPage(array $page): void
    {
        $calls = 0;
        $fetch = static function (array $params) use (&$calls, $page): array {
            $calls++;

            return $page;
        };

        iterator_to_array(Pagination::iterate($fetch, ['cursor' => 'same']), false);

        self::assertSame(1, $calls);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function lastPages(): array
    {
        return [
            'has_more false' => [['data' => [1], 'has_more' => false, 'next_cursor' => 'x']],
            'cursor null' => [['data' => [1], 'has_more' => true, 'next_cursor' => null]],
            'cursor empty' => [['data' => [1], 'has_more' => true, 'next_cursor' => '']],
            'no keys' => [['data' => []]],
            'empty page' => [[]],
            'same cursor again' => [['data' => [1], 'has_more' => true, 'next_cursor' => 'same']],
        ];
    }

    public function testStartsFromAGivenCursor(): void
    {
        $seen = [];
        $fetch = static function (array $params) use (&$seen): array {
            $seen[] = $params;

            return ['data' => [], 'has_more' => false, 'next_cursor' => null];
        };

        iterator_to_array(Pagination::iterate($fetch, ['cursor' => 'start', 'limit' => 1]), false);

        self::assertSame([['cursor' => 'start', 'limit' => 1]], $seen);
    }
}
