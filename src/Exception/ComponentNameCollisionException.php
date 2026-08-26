<?php

declare(strict_types=1);

namespace Neos\OpenApi\Exception;

/**
 * Thrown when two different PHP classes claim the same `#/components/schemas` name — `Blog\Address` and
 * `Billing\Address` both wanting `Address`.
 *
 * Component names are short class names, which is what makes a published document readable. A collision therefore
 * fails loudly: silently renaming one of them to something FQCN-shaped would put a surprising name in a public
 * contract, which is worse than a build error.
 */
final class ComponentNameCollisionException extends \RuntimeException {}
