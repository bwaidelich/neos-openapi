<?php

declare(strict_types=1);

namespace Neos\OpenApi\Response;

use Neos\OpenApi\Binding\TypeBindingProvider;
use Neos\OpenApi\Binding\TypeReference;

/**
 * One [Server-Sent Event](https://html.spec.whatwg.org/multipage/server-sent-events.html#event-stream-interpretation),
 * ready to be yielded from a {@see StreamResponse}'s `stream()`.
 *
 * `data` is either a raw string the caller already formatted ({@see self::create()}), or a `TypeReference` and a
 * value ({@see self::forData()}) — the same split every other response body in this package makes: the *type* is
 * declared here, and {@see self::render()} resolves it through the shared {@see TypeBindingProvider}, so a typed
 * event cannot drift from the schema the document advertised for it (ADR 0005). A value that spans multiple lines
 * becomes one `data:` field per line, exactly as the specification requires.
 */
final readonly class SseEvent
{
    private function __construct(
        public string|null $name,
        public string|null $id,
        public int|null $retry,
        private string|null $rawData,
        private TypeReference|null $dataType,
        private mixed $dataValue,
    ) {}

    public static function create(
        string $data,
        string|null $name = null,
        string|null $id = null,
        int|null $retry = null,
    ): self {
        return new self($name, $id, $retry, $data, null, null);
    }

    public static function forData(
        TypeReference $type,
        mixed $value,
        string|null $name = null,
        string|null $id = null,
        int|null $retry = null,
    ): self {
        return new self($name, $id, $retry, null, $type, $value);
    }

    /**
     * The wire format: `event:`, `id:` and `retry:` fields (each only if declared), one `data:` field per line of
     * the payload, and the blank line that ends the event.
     */
    public function render(TypeBindingProvider $bindings): string
    {
        $data = $this->rawData ?? $this->renderTypedData($bindings);
        $lines = [];
        if ($this->name !== null) {
            $lines[] = 'event: ' . $this->name;
        }
        if ($this->id !== null) {
            $lines[] = 'id: ' . $this->id;
        }
        if ($this->retry !== null) {
            $lines[] = 'retry: ' . $this->retry;
        }
        foreach (explode("\n", $data) as $line) {
            $lines[] = 'data: ' . $line;
        }
        return implode("\n", $lines) . "\n\n";
    }

    private function renderTypedData(TypeBindingProvider $bindings): string
    {
        \assert($this->dataType !== null);
        $serialized = $bindings->for($this->dataType)->serialize($this->dataValue);
        return (string) json_encode($serialized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
