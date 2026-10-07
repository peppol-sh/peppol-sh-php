<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

use PeppolSh\Client;
use PeppolSh\Exception\PeppolException;
use PeppolSh\HttpClient\HttpResponse;
use PeppolSh\Resources\Documents;
use PeppolSh\Testing\FakeHttpClient;
use PeppolSh\Version;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testDefaults(): void
    {
        $client = new Client('ps_live_abc');

        self::assertSame('https://api.peppol.sh', $client->getBaseUrl());
        self::assertSame(30.0, $client->getTimeout());
        self::assertSame(2, $client->getMaxRetries());
        self::assertSame('https://api.peppol.sh', Client::PRODUCTION_BASE_URL);
        self::assertSame('https://sandbox.peppol.sh', Client::SANDBOX_BASE_URL);
    }

    public function testOptionsAreApplied(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json(['status' => 'ok'])]);
        $client = new Client('ps_test_abc', [
            'base_url' => Client::SANDBOX_BASE_URL . '//',
            'timeout' => 5,
            'max_retries' => 0,
            'http_client' => $http,
        ]);

        self::assertSame('https://sandbox.peppol.sh', $client->getBaseUrl());
        self::assertSame(5.0, $client->getTimeout());
        self::assertSame(0, $client->getMaxRetries());

        $client->health();
        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('https://sandbox.peppol.sh/v1/health', $request->getUrl());
        self::assertSame(5.0, $request->getTimeout());
    }

    public function testEmptyApiKeyIsRejected(): void
    {
        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('API key is required');
        new Client('');
    }

    /**
     * @dataProvider invalidApiKeys
     */
    #[DataProvider('invalidApiKeys')]
    public function testApiKeyWithWhitespaceOrControlCharacterIsRejected(string $apiKey): void
    {
        $http = new FakeHttpClient();
        $message = null;
        try {
            new Client($apiKey, ['http_client' => $http]);
        } catch (PeppolException $e) {
            $message = $e->getMessage();
        }

        self::assertNotNull($message, 'Expected a PeppolException');
        self::assertStringContainsString('whitespace or a control character', $message);
        // The message must not show the key.
        self::assertStringNotContainsString('SECRET', $message);
        $http->assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidApiKeys(): array
    {
        return [
            'trailing newline' => ["ps_test_SECRET\n"],
            'trailing CRLF' => ["ps_test_SECRET\r\n"],
            'header injection' => ["SECRET\r\nX-Injected: 1"],
            'leading space' => [' ps_test_SECRET'],
            'space inside' => ['ps_test_ SECRET'],
            'tab' => ["ps_test_SECRET\t"],
            'NUL byte' => ["ps_test_SECRET\0"],
            'DEL' => ["ps_test_SECRET\x7f"],
            'only whitespace' => ['  '],
        ];
    }

    /**
     * @dataProvider invalidOptions
     *
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidOptions')]
    public function testInvalidOptionsAreRejected(array $options, string $message): void
    {
        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage($message);
        new Client('ps_test_abc', $options);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidOptions(): array
    {
        return [
            'unknown key' => [['baseUrl' => 'https://x.test'], 'Unknown client option(s): baseUrl'],
            'base_url not a URL' => [['base_url' => 'sandbox'], '"base_url"'],
            'base_url not a string' => [['base_url' => 5], '"base_url"'],
            'timeout zero' => [['timeout' => 0], '"timeout"'],
            'timeout negative' => [['timeout' => -1.5], '"timeout"'],
            'timeout string' => [['timeout' => '30'], '"timeout"'],
            'timeout INF' => [['timeout' => INF], '"timeout"'],
            'timeout -INF' => [['timeout' => -INF], '"timeout"'],
            'timeout NAN' => [['timeout' => NAN], '"timeout"'],
            'max_retries negative' => [['max_retries' => -1], '"max_retries"'],
            'max_retries float' => [['max_retries' => 1.5], '"max_retries"'],
            'max_retries whole float' => [['max_retries' => 2.0], '"max_retries"'],
            'max_retries string' => [['max_retries' => '3'], '"max_retries"'],
            'http_client wrong type' => [['http_client' => new \stdClass()], '"http_client"'],
            'sleep not callable' => [['sleep' => 'no_such_function_here'], '"sleep"'],
        ];
    }

    public function testHealthSendsAuthAndSdkHeaders(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json(['status' => 'ok', 'checks' => []])]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        self::assertSame(['status' => 'ok', 'checks' => []], $client->health());

        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.peppol.sh/v1/health', $request->getUrl());
        self::assertSame('Bearer ps_test_abc', $request->getHeader('Authorization'));
        self::assertSame('application/json', $request->getHeader('accept'));
        self::assertSame('peppol-sh/sdk-php/' . Version::VERSION, $request->getHeader('x-peppol-sdk'));
        self::assertNull($request->getHeader('content-type'));
        self::assertNull($request->getBody());
    }

    public function testSignupSendsNoAuthorizationHeader(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json(['api_key' => 'ps_test_new'], 201)]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $result = $client->signup(['email' => 'dev@example.com']);

        self::assertSame(['api_key' => 'ps_test_new'], $result);
        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/signup', $request->getPath());
        self::assertNull($request->getHeader('authorization'));
        self::assertSame('application/json', $request->getHeader('content-type'));
        self::assertSame('{"email":"dev@example.com"}', $request->getBody());
    }

    public function testResourcesAreCreatedOneTimeAndCached(): void
    {
        $client = new Client('ps_test_abc', ['http_client' => new FakeHttpClient()]);

        self::assertInstanceOf(Documents::class, $client->documents());
        self::assertSame($client->documents(), $client->documents());
        self::assertSame($client->webhooks(), $client->webhooks());
        self::assertNotSame($client->documents(), (new Client('ps_test_abc'))->documents());
    }

    public function testQueryStringSkipsNullAndEncodesValues(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json([])]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $client->request('GET', '/v1/x', ['a' => 'b c&d', 'skip' => null, 'flag' => true, 'off' => false, 'n' => 10]);

        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('https://api.peppol.sh/v1/x?a=b%20c%26d&flag=true&off=false&n=10', $request->getUrl());
    }

    public function testNonScalarQueryValueIsRejected(): void
    {
        $http = new FakeHttpClient();
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        try {
            $client->request('GET', '/v1/x', ['a' => ['b']]);
            self::fail('Expected a PeppolException');
        } catch (PeppolException $e) {
            self::assertStringContainsString('query value "a"', $e->getMessage());
        }
        $http->assertNothingSent();
    }

    public function testEmptyBodyArrayIsSentAsJsonObject(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json(['key' => 'ps_test_x'], 201)]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $client->account()->createKey();

        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('{}', $request->getBody());
    }

    /**
     * @dataProvider batchBodies
     *
     * @param array<array-key, mixed> $documents
     */
    #[DataProvider('batchBodies')]
    public function testSendBatchBodyIsAlwaysAJsonArray(array $documents, string $expected): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json(['results' => []])]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        // Through reflection: PHPStan permits only a list here, but a caller
        // that does not use PHPStan can pass an array with other keys.
        (new \ReflectionMethod(Documents::class, 'sendBatch'))->invoke($client->documents(), $documents);

        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame($expected, $request->getBody());
    }

    /**
     * @return array<string, array{array<array-key, mixed>, string}>
     */
    public static function batchBodies(): array
    {
        return [
            'list' => [[['number' => 'A'], ['number' => 'B']], '[{"number":"A"},{"number":"B"}]'],
            'gaps in the keys (array_filter)' => [
                array_filter([['number' => 'A'], null, ['number' => 'B']]),
                '[{"number":"A"},{"number":"B"}]',
            ],
            'string keys' => [['inv-1' => ['number' => 'A']], '[{"number":"A"}]'],
            'empty' => [[], '[]'],
        ];
    }

    public function testRequestListFlagSendsAJsonArray(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json([]), FakeHttpClient::json([]), FakeHttpClient::json([])]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $client->request('POST', '/v1/x', [], [], true, true);
        self::assertSame('[]', self::lastBody($http));

        $client->requestRaw('POST', '/v1/x', [], [2 => 'a', 5 => 'b'], true, true);
        self::assertSame('["a","b"]', self::lastBody($http));

        // Without the flag the body is not changed.
        $client->request('POST', '/v1/x', [], [2 => 'a']);
        self::assertSame('{"2":"a"}', self::lastBody($http));
    }

    private static function lastBody(FakeHttpClient $http): ?string
    {
        $request = $http->getLastRequest();
        self::assertNotNull($request);

        return $request->getBody();
    }

    public function testBodyKeepsSlashesAndUnicode(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json([])]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        $client->request('POST', '/v1/x', [], ['url' => 'https://a.test/b', 'name' => 'Café']);

        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('{"url":"https://a.test/b","name":"Café"}', $request->getBody());
    }

    public function testBodyThatCannotBeEncodedThrows(): void
    {
        $client = new Client('ps_test_abc', ['http_client' => new FakeHttpClient()]);

        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('Could not encode');
        $client->request('POST', '/v1/x', [], ['bad' => "\xB1\x31"]);
    }

    public function testResponseDecoding(): void
    {
        $http = new FakeHttpClient([
            new HttpResponse(204),
            new HttpResponse(200, ['Content-Type' => 'application/json'], ''),
            new HttpResponse(200, ['Content-Type' => 'application/xml'], '<Invoice/>'),
            new HttpResponse(200, ['Content-Type' => 'application/problem+json'], '{"a":1}'),
            new HttpResponse(200, ['Content-Type' => 'application/json'], '{not json'),
        ]);
        $client = new Client('ps_test_abc', ['http_client' => $http]);

        self::assertNull($client->request('DELETE', '/v1/x'));
        self::assertNull($client->request('GET', '/v1/x'));
        self::assertSame('<Invoice/>', $client->request('GET', '/v1/x'));
        self::assertSame(['a' => 1], $client->request('GET', '/v1/x'));

        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('not valid JSON');
        $client->request('GET', '/v1/x');
    }

    public function testDebugInfoHidesTheApiKey(): void
    {
        $client = new Client('ps_live_supersecretvalue');

        $dump = print_r($client, true);

        self::assertStringNotContainsString('supersecretvalue', $dump);
        self::assertStringContainsString('ps_live_...', $dump);

        ob_start();
        var_dump($client, $client->documents());
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString('supersecretvalue', $dump);
        self::assertStringContainsString('ps_live_...', $dump);
    }

    public function testDebugInfoShowsNoCharacterOfAKeyWithoutAKnownPrefix(): void
    {
        $dump = print_r(new Client('abc12'), true) . print_r(new Client('supersecretvalue'), true);

        self::assertStringNotContainsString('abc', $dump);
        self::assertStringNotContainsString('supersec', $dump);
        self::assertStringContainsString('=> ...', $dump);
    }

    public function testClientCannotBeSerialized(): void
    {
        // With these options no property is a closure, so PHP itself does not refuse.
        $client = new Client('ps_live_supersecretvalue', ['http_client' => new FakeHttpClient(), 'sleep' => 'usleep']);

        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('cannot be serialized');
        serialize($client);
    }

    public function testResourceCannotBeSerialized(): void
    {
        $client = new Client('ps_live_supersecretvalue', ['http_client' => new FakeHttpClient(), 'sleep' => 'usleep']);

        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('cannot be serialized');
        serialize($client->documents());
    }

    public function testClientCannotBeUnserialized(): void
    {
        $this->expectException(PeppolException::class);
        $this->expectExceptionMessage('cannot be unserialized');
        unserialize('O:15:"PeppolSh\\Client":0:{}');
    }

    public function testClientHasNoStaticState(): void
    {
        $class = new \ReflectionClass(Client::class);

        self::assertSame([], $class->getStaticProperties());
    }
}
