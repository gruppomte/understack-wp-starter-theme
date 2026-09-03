<?php

declare(strict_types=1);

namespace Understack\Controllers;

use Timber\Timber;

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
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    protected function context(array $extra = []): array
    {
        return Timber::context($extra);
    }
}
