<?php

declare(strict_types=1);

namespace Neos\OpenApi\Spec;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Which security schemes satisfy an operation. The alternatives are *or*-ed: any one of them being met is enough.
 *
 * An empty alternative means "no credentials at all is also acceptable", which is how the specification spells
 * optional authentication — {@see self::$anonymousAccessAllowed} names that case, because a request handler has to
 * treat it very differently from a requirement it can simply reject.
 *
 * @see https://spec.openapis.org/oas/v3.1.1#security-requirement-object
 * @implements IteratorAggregate<int, array<string, list<string>>>
 */
final readonly class SecurityRequirementObject implements IteratorAggregate, JsonSerializable
{
    public bool $anonymousAccessAllowed;

    /**
     * @var list<array<string, list<string>>>
     */
    private array $alternatives;

    /**
     * @param list<array<string, list<string>>> $alternatives each one a scheme name => the scopes it must grant
     */
    private function __construct(array $alternatives)
    {
        if ($alternatives === []) {
            throw new \InvalidArgumentException('A Security Requirement Object must list at least one alternative', 1783500170);
        }
        $anonymousAccessAllowed = false;
        foreach ($alternatives as $alternative) {
            if ($alternative === []) {
                $anonymousAccessAllowed = true;
            }
        }
        $this->alternatives = $alternatives;
        $this->anonymousAccessAllowed = $anonymousAccessAllowed;
    }

    /**
     * One scheme, no scopes — the common case (`bearerAuth`).
     */
    public static function scheme(string $name): self
    {
        return new self([[$name => []]]);
    }

    /**
     * One scheme with the scopes it has to grant.
     *
     * @param list<string> $scopes
     */
    public static function scopes(string $name, array $scopes): self
    {
        return new self([[$name => $scopes]]);
    }

    /**
     * @param array<string, list<string>> $schemesAndScopes every scheme in it must be satisfied together
     */
    public static function all(array $schemesAndScopes): self
    {
        return new self([$schemesAndScopes]);
    }

    /**
     * Adds an alternative way of satisfying the requirement.
     *
     * @param array<string, list<string>> $schemesAndScopes
     */
    public function orElse(array $schemesAndScopes): self
    {
        return new self([...$this->alternatives, $schemesAndScopes]);
    }

    /**
     * Adds "unauthenticated is also acceptable" as an alternative.
     */
    public function orAnonymously(): self
    {
        if ($this->anonymousAccessAllowed) {
            return $this;
        }
        return new self([...$this->alternatives, []]);
    }

    /**
     * Every scheme name mentioned by any alternative, so a compiler can check them against the declared schemes.
     *
     * @return list<string>
     */
    public function schemeNames(): array
    {
        $names = [];
        foreach ($this->alternatives as $alternative) {
            foreach (array_keys($alternative) as $name) {
                $names[$name] = true;
            }
        }
        return array_keys($names);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->alternatives);
    }

    /**
     * @return list<array<string, list<string>>>
     */
    public function jsonSerialize(): array
    {
        return $this->alternatives;
    }
}
