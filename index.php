<?php

/**
 * Single template entry point. Every front-end request is routed to a
 * controller that renders a Twig template through Timber.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (! class_exists(Understack\Core\Router::class)) {
    wp_die(
        esc_html__(
            'The theme could not start. Run "composer install" inside the theme directory.',
            'understack-wp-starter'
        )
    );
}

Understack\Core\Router::dispatch();
