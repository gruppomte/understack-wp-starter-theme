<?php

/**
 * Must-use plugin installed by scripts/playground.mjs (--fixture) in the staging
 * Playground: switches on the optional SEO features so the end-to-end suite can
 * verify breadcrumbs and author profiles while the default Playground keeps the
 * shipped defaults (everything off).
 */

declare(strict_types=1);

add_action('after_setup_theme', static function (): void {
    if (! class_exists(Understack\Core\Theme::class)) {
        return;
    }

    add_filter(Understack\Core\Theme::SLUG . '/seo/config', static function (array $config): array {
        $config['breadcrumbs'] = ['enabled' => true];
        $config['structured_data'] = ['author_profiles' => true];

        return $config;
    });
}, 10);
