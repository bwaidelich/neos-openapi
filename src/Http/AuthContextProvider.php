<?php

declare(strict_types=1);

namespace Neos\OpenApi\Http;

use Neos\OpenApi\Spec\SecurityRequirementObject;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Turns the credentials a request carries into the caller an operation's `#[AuthContext]` argument receives.
 *
 * Authentication itself is deliberately not this package's business — it hands over the requirement the document
 * declares and takes back whatever the application considers a caller. The returned object is passed straight to
 * the operation, so it is the application's own type, never one of ours.
 */
interface AuthContextProvider
{
    /**
     * @return object|null the caller, or `null` if the request carries no credentials satisfying the requirement.
     *                     A handler answers `null` with a `401`, unless the requirement allows anonymous access.
     */
    public function authContextFor(ServerRequestInterface $request, SecurityRequirementObject $requirement): object|null;
}
