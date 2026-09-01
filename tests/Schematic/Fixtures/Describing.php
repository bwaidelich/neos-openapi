<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Schematic\Fixtures;

use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * A type that owns a schema and names, as its one member, something nothing can describe.
 *
 * The schema is hand-written on purpose: a derived one could not describe such a member at all, and the point of
 * this fixture is the member the *hoist* walks into — the second place a class name is read off reflection.
 */
final readonly class Describing implements ProvidesSchema
{
    public function __construct(public Undescribable $held) {}

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(held: ObjectSchema::create(additionalProperties: true)),
            required: ['held'],
        );
    }
}
