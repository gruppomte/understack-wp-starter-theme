<?php

declare(strict_types=1);

namespace Understack\Core;

use Understack\ACF\Loader as AcfLoader;
use Understack\ACF\LocalJson as AcfLocalJson;
use Understack\ACF\OptionsPages as AcfOptionsPages;
use Understack\Blocks\Blocks;
use Understack\Context\GlobalContext;
use Understack\Hooks\Frontend;
use Understack\Integrations\WooCommerce;
use Understack\Seo\Seo;
use Understack\Support\Registrable;

/**
 * Composition root: the ordered list of services that make up the theme.
 */
final class Theme
{
    public const SLUG = 'understack-wp-starter';

    public const TEXT_DOMAIN = 'understack-wp-starter';

    /** @var array<class-string<Registrable>> */
    private const SERVICES = [
        Setup::class,
        PageTemplates::class,
        Editor::class,
        Timber::class,
        Assets::class,
        GlobalContext::class,
        Frontend::class,
        AcfLoader::class,
        AcfLocalJson::class,
        AcfOptionsPages::class,
        Blocks::class,
        WooCommerce::class,
        Seo::class,
    ];

    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        foreach (self::SERVICES as $service) {
            (new $service())->register();
        }
    }

    public static function path(string $relative = ''): string
    {
        return get_template_directory() . ($relative !== '' ? '/' . ltrim($relative, '/') : '');
    }

    public static function url(string $relative = ''): string
    {
        return get_template_directory_uri() . ($relative !== '' ? '/' . ltrim($relative, '/') : '');
    }
}
