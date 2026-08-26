<?php

declare(strict_types=1);

namespace Neos\OpenApi\Compilation;

/**
 * One Api Class as registered on an {@see ApiDefinition}: its class-string and the tag its operations are grouped
 * under.
 *
 * Registration is by class-string, never by instance — compiling a document must not require constructing the
 * classes it describes. Instances are resolved at request time.
 */
final readonly class RegisteredApiClass
{
    /**
     * @param class-string $className
     */
    private function __construct(
        public string $className,
        public string $tag,
        public string|null $tagDescription,
    ) {}

    /**
     * @param class-string $className
     * @param string|null $tag defaults to the class's short name, which is what makes a document spanning several
     *                         classes readable in a UI
     */
    public static function create(string $className, string|null $tag = null, string|null $tagDescription = null): self
    {
        return new self($className, $tag ?? self::shortNameOf($className), $tagDescription);
    }

    private static function shortNameOf(string $className): string
    {
        $position = strrpos($className, '\\');
        return $position === false ? $className : substr($className, $position + 1);
    }
}
