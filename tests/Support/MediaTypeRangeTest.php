<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Support;

use Neos\OpenApi\Support\MediaTypeRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaTypeRangeTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function jsonProvider(): array
    {
        return [
            'JSON' => ['application/json', true],
            'with parameters' => ['application/json; charset=utf-8', true],
            'a +json suffix' => ['application/problem+json', true],
            'in upper case' => ['Application/LD+JSON', true],
            'HTML' => ['text/html', false],
            'a +xml suffix' => ['application/rss+xml', false],
            'JSON in another top-level type' => ['text/json', false],
            'a wildcard' => ['application/*', false],
        ];
    }

    #[DataProvider('jsonProvider')]
    public function testItTellsWhetherABodyOfItIsJson(string $mediaType, bool $isJson): void
    {
        self::assertSame($isJson, MediaTypeRange::fromString($mediaType)->isJson());
    }
}
