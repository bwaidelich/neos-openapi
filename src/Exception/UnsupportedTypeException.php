<?php

declare(strict_types=1);

namespace Neos\OpenApi\Exception;

use Neos\OpenApi\Binding\TypeReference;

/**
 * Thrown when no {@see \Neos\OpenApi\Binding\TypeBindingProvider} can describe a type an operation uses.
 */
final class UnsupportedTypeException extends \RuntimeException
{
    public static function for(TypeReference $type, string $reason = ''): self
    {
        return new self(sprintf(
            'Cannot describe the type "%s"%s',
            $type->describe(),
            $reason === '' ? '' : ': ' . $reason,
        ), 1783500201);
    }
}
