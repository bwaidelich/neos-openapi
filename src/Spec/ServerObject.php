<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#server-object
 */
final readonly class ServerObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public string $url,
        public string|null $description = null,
        public ServerVariableObjects|null $variables = null,
    ) {}

    /**
     * The url with its variables substituted, using the given values and falling back to each variable's default.
     *
     * @param array<string, string> $variables
     */
    public function resolvedUrl(array $variables = []): string
    {
        return (string) preg_replace_callback(
            '/{(\w+)}/',
            fn(array $matches): string => $variables[$matches[1]] ?? $this->variables?->defaultValueOf($matches[1]) ?? '',
            $this->url,
        );
    }
}
