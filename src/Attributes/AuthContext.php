<?php

declare(strict_types=1);

namespace Neos\OpenApi\Attributes;

use Attribute;

/**
 * Marks the operation argument that receives the caller's identity, rather than anything from the request.
 *
 * An attribute rather than a marker interface, so an Api Class never has to make its own domain types implement
 * something from this package just to be handed the caller.
 *
 * The argument does not appear in the published document. Compilation rejects it on an operation that no security
 * requirement covers — there would be no caller to hand over.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class AuthContext {}
