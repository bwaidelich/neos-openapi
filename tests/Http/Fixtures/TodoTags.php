<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Http\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * Serializes to a list of strings — which as a header value is the same header sent once per element.
 */
final readonly class TodoTags implements ProvidesSchema
{
    /**
     * @var list<string>
     */
    public array $tags;

    public function __construct(string ...$tags)
    {
        $this->tags = array_values($tags);
    }

    public static function of(string ...$tags): self
    {
        return new self(...$tags);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
