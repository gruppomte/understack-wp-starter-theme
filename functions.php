<?php

/**
 * Thin bootstrap: loads the Composer autoloader and starts the theme.
 * All theme logic lives in the app/ directory.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$themeAutoloader = __DIR__ . '/vendor/autoload.php';

if (! is_readable($themeAutoloader)) {
    add_action('admin_notices', static function (): void {
        wp_admin_notice(
            esc_html__(
                'Theme dependencies are missing. Run "composer install" inside the theme directory.',
                'understack-wp-starter'
            ),
            ['type' => 'error']
        );
    });

    return;
}

require_once $themeAutoloader;

Understack\Core\Theme::boot();
