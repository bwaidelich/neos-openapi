<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#link-object
 */
final readonly class LinkObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    /**
     * @param array<string, mixed>|null $parameters
     */
    public function __construct(
        public string|null $operationRef = null,
        public string|null $operationId = null,
        public array|null $parameters = null,
        public string|null $description = null,
        public ServerObject|null $server = null,
    ) {
        if ($operationRef !== null && $operationId !== null) {
            throw new \InvalidArgumentException('The "operationRef" and "operationId" members of a Link Object are mutually exclusive', 1783500151);
        }
    }
}
