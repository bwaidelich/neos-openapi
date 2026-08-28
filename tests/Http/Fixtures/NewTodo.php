<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * Two properties, so it is a *shape* rather than a class that is one string: a body arrives as
 * `{"title": …}`, and an issue inside it is located at `title`.
 */
final readonly class NewTodo implements ProvidesSchema
{
    public function __construct(
        public string $title,
        public bool $done = false,
    ) {}

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                title: StringSchema::create(minLength: 1),
                done: BooleanSchema::create(),
            ),
            additionalProperties: false,
            required: ['title'],
        );
    }
}
