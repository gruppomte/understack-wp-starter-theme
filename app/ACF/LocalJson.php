<?php

declare(strict_types=1);

namespace Understack\ACF;

use Understack\Core\Theme;
use Understack\Support\Environment;
use Understack\Support\Registrable;

/**
 * Field groups are versioned as JSON in acf-json/ (ACF Local JSON) and edited
 * locally: the field group UI is hidden in production so the database can never
 * drift from the deployed JSON. Workflow: edit locally, commit acf-json/, deploy.
 */
final class LocalJson implements Registrable
{
    public function register(): void
    {
        add_filter('acf/settings/save_json', [$this, 'path']);
        add_filter('acf/json/save_paths', [$this, 'savePaths']);
        add_filter('acf/settings/load_json', [$this, 'loadPaths']);
        add_filter('acf/settings/show_admin', static fn (): bool => ! Environment::isProduction());
    }

    public function path(): string
    {
        return Theme::path('acf-json');
    }

    /**
     * @return string[]
     */
    public function savePaths(): array
    {
        return [$this->path()];
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    public function loadPaths(array $paths): array
    {
        $paths[] = $this->path();

        return array_values(array_unique($paths));
    }
}
