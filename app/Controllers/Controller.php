<?php

declare(strict_types=1);

namespace Understack\Controllers;

use Timber\Timber;
use Understack\Support\Posts;

abstract class Controller
{
    abstract public function handle(): void;

    /**
     * @param string[] $templates Twig templates in order of preference.
     * @param array<string, mixed> $context
     */
    protected function render(array $templates, array $context): void
    {
        Timber::render($templates, $context);
    }

    /**
     * Timber context with the listing caches primed when posts are present.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    protected function context(array $extra = []): array
    {
        $context = Timber::context($extra);

        if (isset($context['posts']) && is_iterable($context['posts'])) {
            Posts::prime($context['posts']);
        }

        return $context;
    }
}
