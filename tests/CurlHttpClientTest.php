<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Client;
use PeppolSh\Exception\ConnectionException;
use PeppolSh\Exception\PeppolException;
use PeppolSh\Exception\ServerException;
use PeppolSh\Exception\TimeoutException;
use PeppolSh\HttpClient\CurlHttpClient;
use PeppolSh\Version;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the default transport against PHP's built-in server on 127.0.0.1.
 * Nothing leaves the machine.
 */
final class CurlHttpClientTest extends TestCase
{
    /** @var resource|null */
    private static $server;

    /** @var string */
    private static $baseUrl = '';

    /** @var int */
    private static $closedPort = 0;

    public static function setUpBeforeClass(): void
    {
        $port = self::freePort();
        self::$closedPort = self::freePort();
        if ($port === 0 || self::$closedPort === 0) {
            return;
        }

        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $process = @proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/Fixtures/server.php'],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            return;
        }

        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if (is_resource($socket)) {
                fclose($socket);
                self::$server = $process;
                self::$baseUrl = 'http://127.0.0.1:' . $port;

                return;
            }
            usleep(50000);
        }

        proc_terminate($process);
        proc_close($process);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        self::$server = null;
    }

    protected function setUp(): void
    {
        if (self::$baseUrl === '') {
            self::markTestSkipped('Could not start the PHP built-in server on 127.0.0.1.');
        }
    }

    private static function freePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0');
        if (!is_resource($socket)) {
            return 0;
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $json): array
    {
        $data = json_decode($json, true);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function testGetSendsHeadersAndReadsTheResponse(): void
    {
        $response = (new CurlHttpClient())->request(
            'GET',
            self::$baseUrl . '/echo?a=1',
            ['accept' => 'application/json', 'x-peppol-sdk' => 'peppol-sh/sdk-php/test'],
            null,
            5.0
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeader('Content-Type'));
        self::assertSame('a, b', $response->getHeader('x-multi'));
        $echo = self::decode($response->getBody());
        self::assertSame('GET', $echo['method']);
        self::assertSame('/echo?a=1', $echo['uri']);
        self::assertSame('', $echo['body']);
        self::assertIsArray($echo['headers']);
        self::assertSame('peppol-sh/sdk-php/test', $echo['headers']['x-peppol-sdk']);
        self::assertArrayNotHasKey('expect', $echo['headers']);
    }

    /**
     * @dataProvider bodyMethods
     */
    #[DataProvider('bodyMethods')]
    public function testBodyIsSentWithTheGivenMethod(string $method): void
    {
        $response = (new CurlHttpClient())->request(
            $method,
            self::$baseUrl . '/echo',
            ['content-type' => 'application/json'],
            '{"name":"Café"}',
            5.0
        );

        $echo = self::decode($response->getBody());
        self::assertSame($method, $echo['method']);
        self::assertSame('{"name":"Café"}', $echo['body']);
        self::assertIsArray($echo['headers']);
        self::assertSame('application/json', $echo['headers']['content-type']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function bodyMethods(): array
    {
        return ['POST' => ['POST'], 'PATCH' => ['PATCH'], 'DELETE' => ['DELETE']];
    }

    public function testPostWithoutBodySendsContentLengthZero(): void
    {
        $response = (new CurlHttpClient())->request('POST', self::$baseUrl . '/echo', [], null, 5.0);

        $echo = self::decode($response->getBody());
        self::assertSame('POST', $echo['method']);
        self::assertSame('', $echo['body']);
        self::assertIsArray($echo['headers']);
        self::assertSame('0', $echo['headers']['content-length'] ?? null);
        self::assertArrayNotHasKey('content-type', $echo['headers']);
    }

    public function testLargeBodyRoundTrips(): void
    {
        $body = (string) json_encode(['data' => str_repeat('x', 200000)]);

        $response = (new CurlHttpClient())->request('POST', self::$baseUrl . '/echo', [], $body, 10.0);

        self::assertSame($body, self::decode($response->getBody())['body']);
    }

    public function testErrorStatusIsReturnedNotThrown(): void
    {
        $response = (new CurlHttpClient())->request('GET', self::$baseUrl . '/unavailable', [], null, 5.0);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('3', $response->getHeader('retry-after'));
    }

    public function testRedirectIsNotFollowed(): void
    {
        $response = (new CurlHttpClient())->request('GET', self::$baseUrl . '/redirect', [], null, 5.0);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/echo', $response->getHeader('location'));
    }

    public function testTimeoutThrowsTimeoutException(): void
    {
        $start = microtime(true);
        try {
            (new CurlHttpClient())->request('GET', self::$baseUrl . '/slow', [], null, 0.2);
            self::fail('Expected a TimeoutException');
        } catch (TimeoutException $e) {
            self::assertSame(0.2, $e->getTimeout());
            self::assertStringContainsString('timed out after 0.2s', $e->getMessage());
        }
        self::assertLessThan(1.2, microtime(true) - $start);
    }

    /**
     * @dataProvider headersWithALineBreak
     *
     * @param array<string, string> $headers
     */
    #[DataProvider('headersWithALineBreak')]
    public function testHeaderWithALineBreakIsRejected(array $headers): void
    {
        $message = null;
        try {
            (new CurlHttpClient())->request('GET', self::$baseUrl . '/echo', $headers, null, 5.0);
        } catch (PeppolException $e) {
            $message = $e->getMessage();
        }

        self::assertNotNull($message, 'Expected a PeppolException');
        self::assertStringContainsString('contains a line break', $message);
        self::assertStringNotContainsString('SECRET', $message);
        self::assertStringNotContainsString("\n", $message);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function headersWithALineBreak(): array
    {
        return [
            'CRLF in the value' => [['authorization' => "Bearer SECRET\r\nX-Injected: 1"]],
            'LF in the value' => [['authorization' => "Bearer SECRET\n"]],
            'CR in the value' => [['x-a' => "SECRET\rb"]],
            'LF in the name' => [["x-a\nX-Injected" => '1']],
        ];
    }

    public function testInfiniteAndVeryLargeTimeoutsAreClamped(): void
    {
        foreach ([INF, 1.0e300, (float) PHP_INT_MAX] as $timeout) {
            $response = (new CurlHttpClient())->request('GET', self::$baseUrl . '/echo', [], null, $timeout);

            self::assertSame(200, $response->getStatusCode());
        }
    }

    public function testTimeoutThatIsNotANumberIsRejected(): void
    {
        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('The timeout must be a number');
        (new CurlHttpClient())->request('GET', self::$baseUrl . '/echo', [], null, NAN);
    }

    public function testNegativeTimeoutGetsTheMinimum(): void
    {
        $this->expectException(TimeoutException::class);
        (new CurlHttpClient())->request('GET', self::$baseUrl . '/slow', [], null, -INF);
    }

    public function testRefusedConnectionThrowsConnectionException(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Could not reach http://127.0.0.1:' . self::$closedPort);
        (new CurlHttpClient())->request('GET', 'http://127.0.0.1:' . self::$closedPort . '/', [], null, 2.0);
    }

    public function testExtraCurlOptionsAreApplied(): void
    {
        $http = new CurlHttpClient([CURLOPT_USERAGENT => 'custom-agent/1']);

        $echo = self::decode($http->request('GET', self::$baseUrl . '/echo', [], null, 5.0)->getBody());

        self::assertIsArray($echo['headers']);
        self::assertSame('custom-agent/1', $echo['headers']['user-agent']);
    }

    public function testClientWithTheDefaultTransportEndToEnd(): void
    {
        $sleeps = [];
        $client = new Client('ps_test_local', [
            'base_url' => self::$baseUrl,
            'timeout' => 5.0,
            'max_retries' => 1,
            'sleep' => static function (float $seconds) use (&$sleeps): void {
                $sleeps[] = $seconds;
            },
        ]);

        $echo = $client->request('POST', '/echo', ['q' => 'a b'], ['company_id' => 'com_1']);
        self::assertIsArray($echo);
        self::assertSame('/echo?q=a%20b', $echo['uri']);
        self::assertSame('{"company_id":"com_1"}', $echo['body']);
        self::assertIsArray($echo['headers']);
        self::assertSame('Bearer ps_test_local', $echo['headers']['authorization']);
        self::assertSame('application/json', $echo['headers']['content-type']);
        self::assertSame('peppol-sh/sdk-php/' . Version::VERSION, $echo['headers']['x-peppol-sdk']);
        self::assertSame('peppol-sh-php/' . Version::VERSION, $echo['headers']['user-agent']);

        self::assertSame('<Invoice/>', $client->request('GET', '/xml'));

        try {
            $client->request('GET', '/unavailable');
            self::fail('Expected a ServerException');
        } catch (ServerException $e) {
            self::assertSame(503, $e->getStatusCode());
            self::assertSame('unavailable', $e->getErrorCode());
            self::assertSame('req_local', $e->getRequestId());
        }
        // One retry, with the Retry-After of 3 s capped at 2 s.
        self::assertSame([2.0], $sleeps);
    }
}
