<?php

declare(strict_types=1);

namespace Understack\Support;

/**
 * Base class for custom taxonomies. Extend it in app/Taxonomies and add the
 * class to the service list in Core\Theme.
 */
abstract class Taxonomy implements Registrable
{
    abstract protected function slug(): string;

    /**
     * Post types the taxonomy is attached to.
     *
     * @return string[]
     */
    abstract protected function objectTypes(): array;

    /**
     * Arguments passed to register_taxonomy().
     *
     * @return array<string, mixed>
     */
    abstract protected function arguments(): array;

    public function register(): void
    {
        add_action('init', function (): void {
            register_taxonomy($this->slug(), $this->objectTypes(), $this->arguments());
        });
    }
}
