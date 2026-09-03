<?php

declare(strict_types=1);

namespace Understack\Core;

use Understack\Support\Registrable;

final class Setup implements Registrable
{
    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'setup']);
    }

    public function setup(): void
    {
        load_theme_textdomain(Theme::TEXT_DOMAIN, Theme::path('languages'));

        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('automatic-feed-links');
        add_theme_support('responsive-embeds');
        add_theme_support('align-wide');
        add_theme_support('html5', [
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'script',
            'style',
            'search-form',
            'navigation-widgets',
        ]);
        add_theme_support('custom-logo', [
            'height' => 120,
            'width' => 400,
            'flex-height' => true,
            'flex-width' => true,
        ]);

        add_theme_support('editor-styles');

        $editorStylesheet = Assets::editorStylesheet();

        if ($editorStylesheet !== null) {
            add_editor_style($editorStylesheet);
        }

        // Sizes used by the theme's own templates; "large" stays the default for content.
        add_image_size('card', 800, 534, true);
        add_image_size('wide', 1600);

        register_nav_menus([
            'primary' => __('Primary navigation', 'understack-wp-starter'),
            'footer' => __('Footer navigation', 'understack-wp-starter'),
        ]);

        if (! isset($GLOBALS['content_width'])) {
            $GLOBALS['content_width'] = 1280;
        }
    }
}
