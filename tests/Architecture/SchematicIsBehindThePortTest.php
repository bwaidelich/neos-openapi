<?php

declare(strict_types=1);

namespace Neos\OpenApi\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Guards the one rule ADR 0002 rests on: `neos/schematic` is reachable from **exactly one** namespace.
 *
 * The core (`Neos\OpenApi\*` — spec model, attributes, generator, request handler) talks to a TypeBinding port;
 * `Neos\OpenApi\Schematic\*` is its sole implementation, and `neos/schematic` is a dev/suggested dependency rather
 * than a hard one. That is what makes extracting a separate `neos/schematic-openapi` package later a
 * composer-manifest change instead of a refactor — and it is worth nothing without this test, because a single
 * stray `use Neos\Schematic\…` in the core would silently make the dependency real.
 *
 * The core's own tests are held to the same rule: they have to prove the core works without the adapter.
 */
final class SchematicIsBehindThePortTest extends TestCase
{
    private const SCHEMATIC_NAMESPACE = 'Neos\\Schematic';

    /**
     * The only places allowed to name `neos/schematic`, relative to the package root.
     */
    private const ADAPTER_PATHS = ['src/Schematic', 'tests/Schematic'];

    public function testCoreSourcesDoNotReferenceSchematic(): void
    {
        self::assertSame([], $this->offendingFiles(__DIR__ . '/../../src'));
    }

    public function testCoreTestsDoNotReferenceSchematic(): void
    {
        self::assertSame([], $this->offendingFiles(__DIR__ . '/../../tests'));
    }

    /**
     * A dependency this package must never grow: `neos/openapi` renders JSON Schema, it does not re-model it.
     */
    public function testNothingReferencesTheAbandonedPredecessor(): void
    {
        foreach (['src', 'tests'] as $directory) {
            foreach ($this->phpFiles(__DIR__ . '/../../' . $directory) as $path => $contents) {
                self::assertStringNotContainsString('Wwwision\\', $contents, $path);
            }
        }
    }

    /**
     * @return list<string> the relative paths of files outside the adapter that name the namespace anyway
     */
    private function offendingFiles(string $directory): array
    {
        $offenders = [];
        foreach ($this->phpFiles($directory) as $path => $contents) {
            foreach (self::ADAPTER_PATHS as $allowed) {
                if (str_starts_with($path, $allowed . '/')) {
                    continue 2;
                }
            }
            if (str_contains($contents, self::SCHEMATIC_NAMESPACE)) {
                $offenders[] = $path;
            }
        }
        sort($offenders);
        return $offenders;
    }

    /**
     * @return iterable<string, string> relative path => contents, excluding this file
     */
    private function phpFiles(string $directory): iterable
    {
        $root = realpath(__DIR__ . '/../..');
        self::assertIsString($root);
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $realPath = (string) $file->getRealPath();
            if ($realPath === __FILE__) {
                continue;
            }
            $contents = file_get_contents($realPath);
            if ($contents === false) {
                continue;
            }
            yield str_replace($root . '/', '', $realPath) => $contents;
        }
    }
}
