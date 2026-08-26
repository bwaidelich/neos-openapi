<?php

declare(strict_types=1);

namespace Neos\OpenApi\Compilation;

use Neos\JsonSchema\ReferenceSchema;
use Neos\JsonSchema\Schema as JsonSchema;
use Neos\OpenApi\Exception\ComponentNameCollisionException;
use Neos\OpenApi\Spec\SchemaObjectMap;

/**
 * The `#/components/schemas` block being assembled while a document is generated.
 *
 * Deliberately **mutable**, and the one such object in this package: hoisting is an accumulation across a whole
 * document, so every type described anywhere in it registers here and gets a `$ref` back. A type used by three
 * operations is one entry referenced three times — which is the point of hoisting, since it makes the document
 * say "these are the same type" rather than repeating an identical schema.
 *
 * Registration is keyed by name but *guarded* by the class it came from, so two different classes claiming the
 * same short name fail loudly instead of one quietly winning.
 */
final class SchemaComponents
{
    /**
     * @var array<string, JsonSchema>
     */
    private array $schemas = [];

    /**
     * @var array<string, class-string> which class claimed each name
     */
    private array $origins = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Whether this name is already taken *by this class* — i.e. whether the schema is already registered.
     *
     * @param class-string $origin
     * @throws ComponentNameCollisionException if the name is taken by a different class
     */
    public function has(string $name, string $origin): bool
    {
        if (!isset($this->origins[$name])) {
            return false;
        }
        if ($this->origins[$name] !== $origin) {
            throw new ComponentNameCollisionException(sprintf(
                'The component name "%s" is claimed by both "%s" and "%s". Give one of them a distinct name.',
                $name,
                $this->origins[$name],
                $origin,
            ), 1783500202);
        }
        return true;
    }

    /**
     * @param class-string $origin the class this schema describes
     * @throws ComponentNameCollisionException
     */
    public function register(string $name, string $origin, JsonSchema $schema): ReferenceSchema
    {
        if (!$this->has($name, $origin)) {
            $this->origins[$name] = $origin;
            $this->schemas[$name] = $schema;
        }
        return SchemaObjectMap::reference($name);
    }

    public function isEmpty(): bool
    {
        return $this->schemas === [];
    }

    public function toSchemaObjectMap(): SchemaObjectMap
    {
        $map = SchemaObjectMap::create();
        // sorted, so the rendered document does not depend on the order operations happened to be visited in
        $names = array_keys($this->schemas);
        sort($names);
        foreach ($names as $name) {
            $map = $map->with($name, $this->schemas[$name]);
        }
        return $map;
    }
}
