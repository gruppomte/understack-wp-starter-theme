<?php

declare(strict_types=1);

namespace Understack\Hooks;

use Understack\Support\Registrable;

/**
 * Front-end output adjustments. Admin behaviour is left untouched.
 */
final class Frontend implements Registrable
{
    public function register(): void
    {
        add_action('init', [$this, 'removeEmojiScripts']);
    }

    /**
     * The emoji detection script and its DNS prefetch are render-blocking
     * assets that modern browsers do not need.
     */
    public function removeEmojiScripts(): void
    {
        if (is_admin()) {
            return;
        }

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('emoji_svg_url', '__return_false');
    }
}
