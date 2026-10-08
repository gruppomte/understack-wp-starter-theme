<?php

declare(strict_types=1);

namespace Understack\Seo\Integrations;

use Understack\Core\Theme;

/**
 * Designed compatibility with WPML, verifiable without the plugin: hreflang
 * stays WPML's (the theme never emits it), canonicals, breadcrumbs and
 * identifiers come from permalink and home URL functions WPML filters per
 * language, and configured About/Contact page IDs are mapped to the current
 * language through WPML's object id filter. Not certified without a real
 * WPML installation (docs/seo-geo/07-compatibility.md).
 */
final class Wpml
{
    /**
     * WPML's core class; checked without autoloading. The version constant alone
     * is not used because ACF reacts to it and expects WPML's globals.
     */
    public static function isActive(): bool
    {
        return class_exists('SitePress', false);
    }

    public function register(): void
    {
        if (! self::isActive()) {
            return;
        }

        add_filter(Theme::SLUG . '/seo/page_id', [$this, 'translatePageId'], 10, 2);
    }

    /**
     * Configured page IDs refer to the original page; WPML returns the translation
     * for the current language, or the original when none exists.
     */
    public function translatePageId(int $id, string $role): int
    {
        return self::translate($id, static fn (int $id): mixed => apply_filters('wpml_object_id', $id, 'page', true));
    }

    /**
     * Pure mapping used by translatePageId(): invalid answers keep the original id.
     *
     * @param callable(int): mixed $translator
     */
    public static function translate(int $id, callable $translator): int
    {
        if ($id <= 0) {
            return $id;
        }

        $translated = $translator($id);

        return is_numeric($translated) && (int) $translated > 0 ? (int) $translated : $id;
    }

    /**
     * @return array{active: bool, version: string, language: string}
     */
    public static function facts(): array
    {
        return [
            'active' => self::isActive(),
            'version' => defined('ICL_SITEPRESS_VERSION') ? (string) constant('ICL_SITEPRESS_VERSION') : '',
            'language' => defined('ICL_LANGUAGE_CODE') ? (string) constant('ICL_LANGUAGE_CODE') : '',
        ];
    }
}
