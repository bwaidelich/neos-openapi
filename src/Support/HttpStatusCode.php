<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

use JsonSerializable;

final readonly class HttpStatusCode implements JsonSerializable
{
    private function __construct(
        public int $value,
    ) {
        if ($value < 100 || $value > 599) {
            throw new \InvalidArgumentException(sprintf('Invalid HTTP status code %d', $value), 1783500101);
        }
    }

    public static function fromInteger(int $value): self
    {
        return new self($value);
    }

    public function jsonSerialize(): int
    {
        return $this->value;
    }
}
