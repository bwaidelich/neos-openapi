<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Http\GeneratorStream;
use Neos\OpenApi\Http\InstanceApiClassResolver;
use Neos\OpenApi\Http\RequestHandler;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Tests\Http\Fixtures\StreamApi;
use PHPUnit\Framework\TestCase;

/**
 * Proves a {@see \Neos\OpenApi\Response\StreamResponse} is served through a {@see GeneratorStream} rather than
 * buffered: the connection carries chunks as they become available, `eof()` only turns `true` once the underlying
 * generator is actually exhausted, and a typed event is rendered through the same binding as everything else
 * (ADR 0005), never `json_encode`'d by the response class itself.
 */
final class StreamingTest extends TestCase
{
    private function handler(): RequestHandler
    {
        $factory = new HttpFactory();
        $compiled = (new ApiCompiler(new FixtureTypeBindingProvider()))->compile(
            ApiDefinition::create(info: new InfoObject(title: 'Streaming', version: '1.0.0'))
                ->withOperationsFrom(StreamApi::class),
        );
        return new RequestHandler(
            $compiled,
            new FixtureTypeBindingProvider(),
            new InstanceApiClassResolver(new StreamApi()),
            $factory,
            $factory,
        );
    }

    public function testATypedSseStreamIsRenderedEventByEventThroughTheSharedBinding(): void
    {
        $response = $this->handler()->handle(new ServerRequest('GET', '/comments'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));

        $body = $response->getBody();
        // deliberately smaller than either event, to prove reads that split across an event boundary still work
        $contents = '';
        while (!$body->eof()) {
            $contents .= $body->read(8);
        }
        self::assertSame(
            "event: comment\ndata: \"Hello\"\n\nevent: comment\ndata: \"World\"\n\n",
            $contents,
        );
    }

    public function testAnUntypedStreamCarriesItsChunksAsIs(): void
    {
        $response = $this->handler()->handle(new ServerRequest('GET', '/heartbeat'));

        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('ping', (string) $response->getBody());
    }

    public function testTheBodyIsAGeneratorStream(): void
    {
        $response = $this->handler()->handle(new ServerRequest('GET', '/heartbeat'));

        self::assertInstanceOf(GeneratorStream::class, $response->getBody());
    }
}
