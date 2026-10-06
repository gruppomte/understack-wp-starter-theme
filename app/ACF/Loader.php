<?php

declare(strict_types=1);

namespace Understack\ACF;

use Understack\Core\Theme;
use Understack\Support\Registrable;

/**
 * Loads the ACF PRO copy bundled in includes/acf when no ACF plugin is active.
 * The license is never stored in the theme: define ACF_PRO_LICENSE in
 * wp-config.php (the updates screen is hidden because the bundled copy is
 * updated through "npm run acf:install").
 */
final class Loader implements Registrable
{
    private const DIRECTORY = 'includes/acf';

    public function register(): void
    {
        if (class_exists('ACF')) {
            return;
        }

        $bootstrap = Theme::path(self::DIRECTORY . '/acf.php');

        if (! is_readable($bootstrap)) {
            return;
        }

        add_filter('acf/settings/url', static fn (): string => Theme::url(self::DIRECTORY) . '/');
        add_filter('acf/settings/show_updates', '__return_false', 100);

        require_once $bootstrap;
    }

    public static function isBundled(): bool
    {
        return is_readable(Theme::path(self::DIRECTORY . '/acf.php'));
    }
}
