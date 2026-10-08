<?php

declare(strict_types=1);

namespace Understack\Seo;

/**
 * Who emits each signal on this request: WordPress core, the detected SEO
 * plugin, the theme's fallback, or nobody. See docs/seo-geo/01-ownership-matrix.md.
 */
final class Ownership
{
    /**
     * @param array<string, Owner> $owners Keyed by Signal value.
     */
    private function __construct(private readonly array $owners)
    {
    }

    public static function resolve(Provider $provider, Config $config): self
    {
        $hasProvider = $provider !== Provider::None;
        $overrides = $config->list('with_provider');
        $coreUnlessProvider = $hasProvider ? Owner::Provider : Owner::Core;

        $fallback = static function (Signal $signal) use ($hasProvider, $overrides, $config): Owner {
            if ($hasProvider && ! in_array($signal->value, $overrides, true)) {
                return Owner::Provider;
            }

            return $config->bool('fallbacks.' . $signal->value) ? Owner::Theme : Owner::None;
        };

        $canonical = $fallback(Signal::Canonical);
        $structuredData = $fallback(Signal::StructuredData);

        return new self([
            Signal::Title->value => $coreUnlessProvider,
            Signal::Description->value => $fallback(Signal::Description),
            // Theme means "core on singular pages, theme on archives and listings".
            Signal::Canonical->value => $canonical === Owner::Theme ? Owner::Theme : $coreUnlessProvider,
            Signal::Robots->value => $coreUnlessProvider,
            Signal::Social->value => $fallback(Signal::Social),
            Signal::Sitemap->value => $coreUnlessProvider,
            Signal::RobotsTxt->value => $coreUnlessProvider,
            Signal::StructuredData->value => $structuredData,
            // The BreadcrumbList node belongs to whoever emits the graph; the visual trail is independent.
            Signal::Breadcrumbs->value => $hasProvider && $structuredData !== Owner::Theme
                ? Owner::Provider
                : ($structuredData === Owner::Theme && $config->bool('breadcrumbs.enabled')
                    ? Owner::Theme
                    : Owner::None),
        ]);
    }

    public function of(Signal $signal): Owner
    {
        return $this->owners[$signal->value] ?? Owner::None;
    }

    public function themeOwns(Signal $signal): bool
    {
        return $this->of($signal) === Owner::Theme;
    }

    /**
     * Core output that the theme may complete (no provider in charge).
     */
    public function coreOwns(Signal $signal): bool
    {
        return $this->of($signal) === Owner::Core;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_map(static fn (Owner $owner): string => $owner->value, $this->owners);
    }
}
