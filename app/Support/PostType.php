<?php

declare(strict_types=1);

namespace Understack\Support;

/**
 * Base class for custom post types. Extend it in app/PostTypes and add the
 * class to the service list in Core\Theme.
 */
abstract class PostType implements Registrable
{
    abstract protected function slug(): string;

    /**
     * Arguments passed to register_post_type().
     *
     * @return array<string, mixed>
     */
    abstract protected function arguments(): array;

    public function register(): void
    {
        add_action('init', function (): void {
            register_post_type($this->slug(), $this->arguments());
        });
    }
}
