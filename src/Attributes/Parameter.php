<?php

declare(strict_types=1);

namespace Neos\OpenApi\Attributes;

use Attribute;
use Neos\OpenApi\Support\ParameterLocation;

/**
 * Overrides where an operation argument is read from, and under what name.
 *
 * Without it, an argument named in the path template is a path parameter and anything else is a query parameter.
 * A path parameter's name always comes from the template, so naming one here is an error rather than an override.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Parameter
{
    public readonly ParameterLocation $in;

    public function __construct(
        ParameterLocation|string $in,
        public readonly string|null $name = null,
        public readonly string|null $description = null,
        public readonly bool|null $deprecated = null,
    ) {
        $this->in = is_string($in) ? ParameterLocation::from($in) : $in;
        if ($this->in === ParameterLocation::path && $name !== null) {
            throw new \InvalidArgumentException(sprintf('A path parameter takes its name from the path template, so "%s" cannot be renamed', $name), 1783500300);
        }
    }
}
