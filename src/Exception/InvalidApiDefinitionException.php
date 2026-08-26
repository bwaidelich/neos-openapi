<?php

declare(strict_types=1);

namespace Neos\OpenApi\Exception;

/**
 * Thrown while compiling an ApiDefinition whose classes do not add up to a valid API — a duplicated operationId, an
 * argument nothing can fill, an auth context on an unsecured operation.
 *
 * Every one of these is a mistake in the *code being described*, caught once at compile time rather than surfacing
 * as a confusing response later.
 */
final class InvalidApiDefinitionException extends \RuntimeException {}
