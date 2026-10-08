<?php

/**
 * Must-use plugin installed by scripts/playground.mjs (--fixture) in the staging
 * Playground: switches on the optional SEO features and simulates the
 * integrations so the end-to-end suite can verify them, while the default
 * Playground keeps the shipped defaults (everything off):
 *  - breadcrumbs and author profiles;
 *  - a custom crawler policy with synthetic user-agent tokens;
 *  - a WPML mock: WPML's core class name (the theme detects it) and the object id
 *    filter, which maps the configured Contact page to the Team page as if it
 *    were its translation. The version constant is deliberately not defined:
 *    ACF would switch on its own WPML layer and expect WPML's globals.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects -- test fixture: declares the WPML mock class and registers filters.

if (! class_exists('SitePress', false)) {
    // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- WPML's global class name, mocked for the tests.
    final class SitePress
    {
    }
}

add_action('after_setup_theme', static function (): void {
    if (! class_exists(Understack\Core\Theme::class)) {
        return;
    }

    add_filter(Understack\Core\Theme::SLUG . '/seo/config', static function (array $config): array {
        $contact = get_page_by_path('contact');

        $config['breadcrumbs'] = ['enabled' => true];
        $config['structured_data'] = [
            'author_profiles' => true,
            'contact_page' => $contact instanceof WP_Post ? (int) $contact->ID : 0,
        ];
        $config['crawlers'] = [
            'policy' => 'custom',
            'groups' => [
                ['agents' => ['ExampleTrainingBot'], 'disallow' => ['/']],
                ['agents' => ['*'], 'disallow' => ['/private/'], 'crawl_delay' => 5],
            ],
        ];

        return $config;
    });
}, 10);

// WPML mock: the "translation" of the Contact page is the Team page.
add_filter('wpml_object_id', static function ($id, $type) {
    $contact = get_page_by_path('contact');
    $team = get_page_by_path('about/team');

    $isContact = $contact instanceof WP_Post && (int) $id === (int) $contact->ID;

    if ($type === 'page' && $isContact && $team instanceof WP_Post) {
        return (int) $team->ID;
    }

    return $id;
}, 10, 2);
