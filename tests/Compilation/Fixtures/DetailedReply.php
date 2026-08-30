<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Compilation\Fixtures;

use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;

final class DetailedReply extends Reply
{
    public function __construct(string $text, public readonly string $detail)
    {
        parent::__construct($text);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            title: 'DetailedReply',
            properties: ObjectProperties::create(text: StringSchema::create(), detail: StringSchema::create()),
            required: ['text', 'detail'],
        );
    }
}
