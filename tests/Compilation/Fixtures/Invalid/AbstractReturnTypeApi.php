<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures\Invalid;

use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\OpenApi\Attributes\Operation;

abstract readonly class Shapeless implements ProvidesSchema
{
    public static function schema(): Schema
    {
        return ObjectSchema::create(title: 'Shapeless', properties: ObjectProperties::create());
    }
}

final class AbstractReturnTypeApi
{
    /**
     * A type that cannot be constructed describes nothing this package can serve: a body carrying one could never
     * be read back into it, so it is refused while compiling rather than published.
     */
    #[Operation(path: '/shapeless', method: 'GET')]
    public function shapeless(): Shapeless
    {
        throw new \LogicException('never called', 1783500416);
    }
}
