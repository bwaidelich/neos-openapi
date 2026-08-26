<?php

declare(strict_types=1);

namespace Neos\OpenApi\Http;

use Psr\Http\Message\StreamInterface;

/**
 * A read-only, one-way {@see StreamInterface} that pulls from a {@see \Generator} of `string` chunks on demand.
 *
 * What a {@see \Neos\OpenApi\Response\StreamResponse} is served through: the PSR-17 `StreamFactoryInterface` this
 * package otherwise relies on has no way to build a stream that produces its content over time rather than all at
 * once, so this is constructed directly instead (ADR 0007).
 *
 * Not seekable, not writable, and its size is never known up front — all three are true of anything actually being
 * streamed, and pretending otherwise would be the lie a real streaming response cannot afford.
 */
final class GeneratorStream implements StreamInterface
{
    private string $buffer = '';
    private bool $finished = false;
    private int $position = 0;

    /**
     * @param \Generator<mixed, string> $source
     */
    public function __construct(private \Generator|null $source) {}

    public function __toString(): string
    {
        try {
            return $this->getContents();
        } catch (\Throwable) {
            // __toString() must not throw (PSR-7)
            return '';
        }
    }

    public function close(): void
    {
        $this->source = null;
        $this->finished = true;
    }

    public function detach()
    {
        $this->close();
        return null;
    }

    public function getSize(): int|null
    {
        return null;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        if ($this->buffer !== '') {
            return false;
        }
        if ($this->finished) {
            return true;
        }
        // the buffer being empty does not by itself mean the source is exhausted — find out
        return !$this->pull();
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new \RuntimeException('A generator-backed stream cannot seek', 1783500500);
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new \RuntimeException('A generator-backed stream is read-only', 1783500501);
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        while (\strlen($this->buffer) < $length && $this->pull()) {
            // fill the buffer until it satisfies the request or the source is exhausted
        }
        $chunk = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, \strlen($chunk));
        $this->position += \strlen($chunk);
        return $chunk;
    }

    public function getContents(): string
    {
        $contents = '';
        while (!$this->eof()) {
            $contents .= $this->read(8192);
        }
        return $contents;
    }

    public function getMetadata(string|null $key = null): mixed
    {
        return $key === null ? [] : null;
    }

    /**
     * Pulls the next chunk from the generator into the buffer. Returns whether there was one.
     */
    private function pull(): bool
    {
        if ($this->source === null) {
            return false;
        }
        if (!$this->source->valid()) {
            $this->finished = true;
            $this->source = null;
            return false;
        }
        $this->buffer .= $this->source->current();
        $this->source->next();
        return true;
    }
}
