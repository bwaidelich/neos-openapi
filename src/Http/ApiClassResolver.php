<?php

declare(strict_types=1);

namespace Neos\OpenApi\Http;

/**
 * Turns the class-string a Dispatch Table entry names into the instance to call the operation on.
 *
 * An Api Class is registered by class-string — generation only ever needs the name — so serving needs one of
 * these, and it is asked at request time rather than at compile time: a compiled API stays plain data that way,
 * and an Api Class may perfectly well be request-scoped.
 */
interface ApiClassResolver
{
    /**
     * @param class-string $className
     * @return object an instance of $className
     */
    public function resolve(string $className): object;
}
