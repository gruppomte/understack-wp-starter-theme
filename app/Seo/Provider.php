<?php

declare(strict_types=1);

namespace Understack\Seo;

/**
 * SEO plugins the theme recognises. Detection relies on the version constant
 * each plugin defines while it is active: an installed but inactive plugin
 * defines nothing and leaves the theme in fallback mode.
 */
enum Provider: string
{
    case None = 'none';
    case RankMath = 'rank-math';
    case Yoast = 'yoast';
    case SeoPress = 'seopress';
    case Aioseo = 'aioseo';
    case TheSeoFramework = 'the-seo-framework';
    case SlimSeo = 'slim-seo';

    /**
     * Detects the active provider through the given predicate (normally "defined").
     *
     * @param callable(string): bool $isDefined
     */
    public static function detect(callable $isDefined): self
    {
        foreach (self::cases() as $provider) {
            $constant = $provider->constant();

            if ($constant !== null && $isDefined($constant)) {
                return $provider;
            }
        }

        return self::None;
    }

    /**
     * Version constant defined by the plugin while it is active.
     */
    public function constant(): ?string
    {
        return match ($this) {
            self::None => null,
            self::RankMath => 'RANK_MATH_VERSION',
            self::Yoast => 'WPSEO_VERSION',
            self::SeoPress => 'SEOPRESS_VERSION',
            self::Aioseo => 'AIOSEO_VERSION',
            self::TheSeoFramework => 'THE_SEO_FRAMEWORK_VERSION',
            self::SlimSeo => 'SLIM_SEO_VER',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'none',
            self::RankMath => 'Rank Math',
            self::Yoast => 'Yoast SEO',
            self::SeoPress => 'SEOPress',
            self::Aioseo => 'All in One SEO',
            self::TheSeoFramework => 'The SEO Framework',
            self::SlimSeo => 'Slim SEO',
        };
    }

    /**
     * Whether the theme measured this provider's front-end output (docs/seo-geo/00-audit.md).
     * The others are detected but their behaviour is not certified.
     */
    public function isVerified(): bool
    {
        return in_array($this, [self::None, self::RankMath, self::Yoast], true);
    }
}
