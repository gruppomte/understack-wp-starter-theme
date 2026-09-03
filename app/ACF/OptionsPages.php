<?php

declare(strict_types=1);

namespace Understack\ACF;

use Understack\Support\Registrable;

final class OptionsPages implements Registrable
{
    public function register(): void
    {
        // Option values are autoloaded with the other site options instead of one query per field.
        add_filter('acf/settings/autoload', '__return_true');
        add_action('acf/init', [$this, 'registerPages']);
    }

    public function registerPages(): void
    {
        if (! function_exists('acf_add_options_page')) {
            return;
        }

        acf_add_options_page([
            'page_title' => __('Site Settings', 'understack-wp-starter'),
            'menu_title' => __('Site Settings', 'understack-wp-starter'),
            'menu_slug' => 'site-settings',
            'capability' => 'edit_theme_options',
            'redirect' => false,
            'autoload' => true,
        ]);
    }
}
