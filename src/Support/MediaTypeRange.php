<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

use JsonSerializable;

/**
 * A media type, possibly with wildcards — `application/json`, `application/*`, `*\/*` — as used for the keys of a
 * Media Type Object map and for matching an incoming `Content-Type` against them.
 */
final class MediaTypeRange implements JsonSerializable
{
    private const PATTERN = '/^(?P<type>[.!#%&\'`^~$*+\-|\w]+)\/(?P<subtype>[.!#%&\'`^~$*+\-|\w]+)(?P<parameters>.*)$/i';

    public readonly string $type;
    public readonly string $subtype;

    private function __construct(
        public readonly string $value,
    ) {
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid media type range "%s"', $value), 1783500104);
        }
        $this->type = $matches['type'];
        $this->subtype = $matches['subtype'];
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Whether this range covers the given (concrete) media type. Wildcards widen *this* side only.
     */
    public function matches(self $other): bool
    {
        return ($this->type === '*' || $this->type === $other->type)
            && ($this->subtype === '*' || $this->subtype === $other->subtype);
    }

    /**
     * Whether this range names one specific media type rather than a family of them.
     */
    public function isConcrete(): bool
    {
        return $this->type !== '*' && $this->subtype !== '*';
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
