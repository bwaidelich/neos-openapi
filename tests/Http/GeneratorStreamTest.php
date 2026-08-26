<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http;

use Neos\OpenApi\Http\GeneratorStream;
use PHPUnit\Framework\TestCase;

final class GeneratorStreamTest extends TestCase
{
    /**
     * @param list<string> $chunks
     */
    private function stream(array $chunks): GeneratorStream
    {
        return new GeneratorStream((static function () use ($chunks): \Generator {
            yield from $chunks;
        })());
    }

    public function testReadPullsFromTheGeneratorAsNeeded(): void
    {
        $stream = $this->stream(['ab', 'cde', 'f']);

        // a read size that matches none of the underlying chunk boundaries
        self::assertSame('abc', $stream->read(3));
        self::assertSame('def', $stream->read(3));
        self::assertTrue($stream->eof());
    }

    public function testEofIsFalseUntilTheGeneratorAndTheBufferAreBothExhausted(): void
    {
        $stream = $this->stream(['ab']);

        self::assertFalse($stream->eof());
        $stream->read(1);
        self::assertFalse($stream->eof(), 'one byte is still buffered');
        $stream->read(1);
        self::assertTrue($stream->eof());
    }

    public function testGetContentsReadsEverythingRemaining(): void
    {
        $stream = $this->stream(['one', 'two', 'three']);

        self::assertSame('one', $stream->read(3));
        self::assertSame('twothree', $stream->getContents());
        self::assertTrue($stream->eof());
    }

    public function testToStringReadsEverythingAndNeverThrows(): void
    {
        $stream = $this->stream(['a', 'b', 'c']);

        self::assertSame('abc', (string) $stream);
    }

    public function testAnEmptyGeneratorIsImmediatelyAtEof(): void
    {
        $stream = $this->stream([]);

        self::assertTrue($stream->eof());
        self::assertSame('', $stream->read(10));
    }

    public function testItIsReadOnlyAndNotSeekable(): void
    {
        $stream = $this->stream(['x']);

        self::assertTrue($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
        self::assertNull($stream->getSize());
    }

    public function testWritingThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->stream(['x'])->write('y');
    }

    public function testSeekingThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->stream(['x'])->seek(0);
    }

    public function testTellTracksBytesRead(): void
    {
        $stream = $this->stream(['abcdef']);

        self::assertSame(0, $stream->tell());
        $stream->read(4);
        self::assertSame(4, $stream->tell());
    }
}
