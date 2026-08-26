<?php

declare(strict_types=1);

namespace Neos\OpenApi\Exception;

/**
 * Thrown when two paths in one document would match the same requests — either literally the same path and
 * method twice, or two templates differing only in what they call their variables (`/users/{id}` vs
 * `/users/{userId}`), which the specification forbids.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#paths-object
 */
final class AmbiguousPathException extends \RuntimeException {}
