<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#server-variable-object
 */
final readonly class ServerVariableObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    /**
     * @param list<string>|null $enum
     */
    public function __construct(
        public string $default,
        public array|null $enum = null,
        public string|null $description = null,
    ) {
        if ($enum !== null && !in_array($default, $enum, true)) {
            throw new \InvalidArgumentException(sprintf('The default "%s" of a Server Variable Object must be one of its enum values', $default), 1783500121);
        }
    }
}
