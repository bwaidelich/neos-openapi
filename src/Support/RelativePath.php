<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

use JsonSerializable;

/**
 * A path template as it appears as a key of the Paths Object — `/users/{id}` — with the matching that turns a
 * concrete request path back into its template variables.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#path-templating
 */
final class RelativePath implements JsonSerializable
{
    private const PLACEHOLDER_PATTERN = '/\{([^\/]+)}/';

    private readonly string $regex;

    private function __construct(
        public readonly string $value,
    ) {
        if (!str_starts_with($value, '/')) {
            throw new \InvalidArgumentException(sprintf('A path must start with a forward slash, got "%s"', $value), 1783500102);
        }
        $pattern = preg_replace(self::PLACEHOLDER_PATTERN, '(?<$1>([^\/]+))', str_replace('/', '\/', $value));
        if (!is_string($pattern)) {
            throw new \InvalidArgumentException(sprintf('Failed to derive a matching pattern from path "%s"', $value), 1783500103);
        }
        $this->regex = '/^' . $pattern . '$/i';
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function containsPlaceholder(string $placeholder): bool
    {
        return str_contains($this->value, '{' . $placeholder . '}');
    }

    /**
     * The names of the variables in this template, in the order they appear.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        if (preg_match_all(self::PLACEHOLDER_PATTERN, $this->value, $matches) === false) {
            return [];
        }
        return $matches[1];
    }

    public function isTemplated(): bool
    {
        return preg_match(self::PLACEHOLDER_PATTERN, $this->value) === 1;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Whether both templates describe the same hierarchy, differing only in what they *call* their variables.
     *
     * The specification forbids two such paths in one document: `/users/{id}` and `/users/{userId}` would match
     * the same requests, so they are the same path under two names.
     *
     * @see https://spec.openapis.org/oas/v3.1.1#paths-object
     */
    public function equalsStructurally(self $other): bool
    {
        return $this->structure() === $other->structure();
    }

    /**
     * @param array<string, string>|null $variables set to the template variables extracted from $path
     */
    public function matches(string $path, array|null &$variables = null): bool
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return false;
        }
        /** @var array<string, string> $named */
        $named = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        $variables = $named;
        return true;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    private function structure(): string
    {
        return (string) preg_replace(self::PLACEHOLDER_PATTERN, '{}', $this->value);
    }
}
