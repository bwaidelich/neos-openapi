<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use JsonSerializable;
use Neos\OpenApi\Support\SerializesNonNullMembers;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#oauth-flows-object
 */
final readonly class OAuthFlowsObject implements JsonSerializable
{
    use SerializesNonNullMembers;

    public function __construct(
        public OAuthFlowObject|null $implicit = null,
        public OAuthFlowObject|null $password = null,
        public OAuthFlowObject|null $clientCredentials = null,
        public OAuthFlowObject|null $authorizationCode = null,
    ) {
        if ($implicit === null && $password === null && $clientCredentials === null && $authorizationCode === null) {
            throw new \InvalidArgumentException('An OAuth Flows Object must configure at least one flow', 1783500160);
        }
        if ($implicit !== null && $implicit->authorizationUrl === null) {
            throw new \InvalidArgumentException('The "implicit" flow requires an "authorizationUrl"', 1783500161);
        }
        if ($password !== null && $password->tokenUrl === null) {
            throw new \InvalidArgumentException('The "password" flow requires a "tokenUrl"', 1783500162);
        }
        if ($clientCredentials !== null && $clientCredentials->tokenUrl === null) {
            throw new \InvalidArgumentException('The "clientCredentials" flow requires a "tokenUrl"', 1783500163);
        }
        if ($authorizationCode !== null && ($authorizationCode->authorizationUrl === null || $authorizationCode->tokenUrl === null)) {
            throw new \InvalidArgumentException('The "authorizationCode" flow requires both an "authorizationUrl" and a "tokenUrl"', 1783500164);
        }
    }
}
