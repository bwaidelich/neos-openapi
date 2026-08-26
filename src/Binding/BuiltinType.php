<?php

declare(strict_types=1);

namespace Neos\OpenApi\Binding;

/**
 * The PHP scalar types an operation argument may have. Everything else is a class.
 */
enum BuiltinType: string
{
    case string = 'string';
    case int = 'int';
    case float = 'float';
    case bool = 'bool';
}
