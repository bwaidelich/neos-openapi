<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * Which urls a flow needs depends on the flow, so the named constructors of {@see OAuthFlowsObject} are the
 * intended way in — they enforce the combinations the specification allows.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#oauth-flow-object
 */
final readonly class OAuthFlowObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    /**
     * @param array<string, string> $scopes scope name => its description
     */
    public function __construct(
        public array $scopes,
        public string|null $authorizationUrl = null,
        public string|null $tokenUrl = null,
        public string|null $refreshUrl = null,
    ) {}
}
