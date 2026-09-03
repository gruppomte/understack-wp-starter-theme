<?php

declare(strict_types=1);

namespace Understack\Integrations;

use Understack\Core\Assets;
use Understack\Core\Theme;
use Understack\Support\Registrable;

/**
 * Minimal WooCommerce integration layer. Inactive unless the plugin is present.
 * Templates render through views/woocommerce/ via woocommerce.php, and a small
 * stylesheet bridges WooCommerce markup with the theme.
 */
final class WooCommerce implements Registrable
{
    private const ENTRY_STYLE = 'src/css/woocommerce.css';

    public function register(): void
    {
        if (! class_exists('WooCommerce')) {
            return;
        }

        add_action('after_setup_theme', [$this, 'addSupport']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 20);
    }

    public function addSupport(): void
    {
        add_theme_support('woocommerce');
        add_theme_support('wc-product-gallery-zoom');
        add_theme_support('wc-product-gallery-lightbox');
        add_theme_support('wc-product-gallery-slider');
    }

    public function enqueue(): void
    {
        $handle = Theme::SLUG . '-woocommerce';
        $devServer = Assets::devServer();

        if ($devServer !== null) {
            wp_enqueue_style($handle, $devServer . '/' . self::ENTRY_STYLE, [], null);
            return;
        }

        $url = Assets::buildEntryUrl(self::ENTRY_STYLE);

        if ($url !== null) {
            wp_enqueue_style($handle, $url, [], null);
        }
    }
}
