<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use IteratorAggregate;
use JsonSerializable;
use Neos\OpenApi\Exception\AmbiguousPathException;
use Neos\OpenApi\Support\RelativePath;
use Traversable;

/**
 * Path templates to the operations available on them.
 *
 * Insertion order matters and is not the order the paths were added: a concrete path is kept ahead of any template
 * that would also match it, so `/users/me` wins over `/users/{id}` when a request comes in. Two templates that
 * differ only in what they call their variables are rejected outright — the specification treats them as the same
 * path.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#paths-object
 * @implements IteratorAggregate<string, PathObject>
 */
final readonly class PathsObject implements IteratorAggregate, JsonSerializable
{
    /**
     * @var list<array{path: RelativePath, object: PathObject}>
     */
    private array $items;

    /**
     * @param list<array{path: RelativePath, object: PathObject}> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function create(): self
    {
        return new self([]);
    }

    /**
     * @throws AmbiguousPathException if the path is already present, or is structurally identical to one that is
     */
    public function with(RelativePath $path, PathObject $object): self
    {
        $entry = ['path' => $path, 'object' => $object];
        $items = $this->items;
        foreach ($items as $index => $item) {
            if ($path->equalsStructurally($item['path'])) {
                throw new AmbiguousPathException(sprintf(
                    'The path "%s" is ambiguous with "%s" already in this document',
                    $path->value,
                    $item['path']->value,
                ), 1783500180);
            }
            // a concrete path must be matched before a template that would also swallow it
            if (!$path->isTemplated() && $item['path']->isTemplated() && $item['path']->matches($path->value)) {
                array_splice($items, $index, 0, [$entry]);
                return new self($items);
            }
        }
        return new self([...$items, $entry]);
    }

    /**
     * Replaces the Path Item Object at an existing path, keeping its position — which matters, since ordering is
     * what makes a concrete path win over a template that would also match it.
     */
    public function replace(RelativePath $path, PathObject $object): self
    {
        $items = $this->items;
        foreach ($items as $index => $item) {
            if ($item['path']->equals($path)) {
                $items[$index] = ['path' => $path, 'object' => $object];
                return new self($items);
            }
        }
        throw new \InvalidArgumentException(sprintf('The path "%s" is not in this document', $path->value), 1783500181);
    }

    public function get(RelativePath $path): PathObject|null
    {
        foreach ($this->items as $item) {
            if ($item['path']->equals($path)) {
                return $item['object'];
            }
        }
        return null;
    }

    /**
     * The first path template matching a concrete request path, with its variables extracted.
     *
     * @param array<string, string>|null $variables
     */
    public function match(string $path, array|null &$variables = null): PathObject|null
    {
        $template = $this->matchTemplate($path, $variables);
        return $template === null ? null : $this->get($template);
    }

    /**
     * The *template* the first matching entry is keyed by, with the request path's variables extracted.
     *
     * What a request handler needs: the template is the key everything else about an operation is filed under —
     * the Dispatch Table above all — so matching has to hand back the key it matched, not only what it found.
     *
     * @param array<string, string>|null $variables
     */
    public function matchTemplate(string $path, array|null &$variables = null): RelativePath|null
    {
        foreach ($this->items as $item) {
            if ($item['path']->matches($path, $variables)) {
                return $item['path'];
            }
        }
        return null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function getIterator(): Traversable
    {
        foreach ($this->items as $item) {
            yield $item['path']->value => $item['object'];
        }
    }

    /**
     * @return array<string, PathObject>
     */
    public function jsonSerialize(): array
    {
        return iterator_to_array($this);
    }
}
