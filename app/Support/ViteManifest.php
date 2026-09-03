<?php

declare(strict_types=1);

namespace Understack\Support;

/**
 * Reads the manifest written by "vite build" so WordPress can enqueue the
 * hashed production files without hardcoding their names.
 */
final class ViteManifest
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $entries = null;

    public function __construct(private readonly string $path)
    {
    }

    public function exists(): bool
    {
        return is_readable($this->path);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entry(string $name): ?array
    {
        $entry = $this->entries()[$name] ?? null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $this->entries = [];

        if (! $this->exists()) {
            return $this->entries;
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);

        if (is_array($decoded)) {
            $this->entries = $decoded;
        }

        return $this->entries;
    }

    /**
     * CSS files an entry depends on, including CSS pulled in by statically imported chunks.
     *
     * @return string[]
     */
    public function css(string $name): array
    {
        $files = [];
        $seen = [];

        $this->collectCss($name, $files, $seen);

        return array_values(array_unique($files));
    }

    /**
     * @param string[] $files
     * @param array<string, bool> $seen
     */
    private function collectCss(string $name, array &$files, array &$seen): void
    {
        if (isset($seen[$name])) {
            return;
        }

        $seen[$name] = true;
        $entry = $this->entry($name);

        if ($entry === null) {
            return;
        }

        foreach ((array) ($entry['imports'] ?? []) as $import) {
            $this->collectCss((string) $import, $files, $seen);
        }

        foreach ((array) ($entry['css'] ?? []) as $css) {
            $files[] = (string) $css;
        }
    }
}
