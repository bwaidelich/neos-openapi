<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Response;

use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\SseEvent;
use Neos\OpenApi\Tests\Compilation\StubTypeBindingProvider;
use PHPUnit\Framework\TestCase;

final class SseEventTest extends TestCase
{
    public function testRawDataRendersWithNoFieldsDeclared(): void
    {
        $event = SseEvent::create('hello');

        self::assertSame("data: hello\n\n", $event->render(new StubTypeBindingProvider()));
    }

    public function testDeclaredFieldsRenderInOrderBeforeData(): void
    {
        $event = SseEvent::create('hello', name: 'greeting', id: '42', retry: 3000);

        self::assertSame(
            "event: greeting\nid: 42\nretry: 3000\ndata: hello\n\n",
            $event->render(new StubTypeBindingProvider()),
        );
    }

    public function testMultiLineDataBecomesOneDataFieldPerLine(): void
    {
        $event = SseEvent::create("first\nsecond\nthird");

        self::assertSame("data: first\ndata: second\ndata: third\n\n", $event->render(new StubTypeBindingProvider()));
    }

    /**
     * The stub provider's binding serializes a value as-is (see {@see StubTypeBindingProvider}), so the rendered
     * `data:` field is that value's JSON encoding — never something the event itself formatted.
     */
    public function testTypedDataIsResolvedAndJsonEncodedThroughTheBinding(): void
    {
        $event = SseEvent::forData(TypeReference::builtin(BuiltinType::string), 'hello world');

        self::assertSame('data: "hello world"' . "\n\n", $event->render(new StubTypeBindingProvider()));
    }
}
