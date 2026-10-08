<?php

declare(strict_types=1);

namespace Understack\Seo;

/**
 * Keeps the core XML sitemap coherent with the robots policy. Nothing here
 * runs while an SEO plugin owns the sitemap (plugins replace core's).
 */
final class Sitemap
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function register(): void
    {
        add_filter('wp_sitemaps_add_provider', [$this, 'provider'], 10, 2);
        add_filter('wp_sitemaps_post_types', [$this, 'postTypes']);
        add_filter('wp_sitemaps_taxonomies', [$this, 'taxonomies']);
    }

    /**
     * The users sitemap lists author archives and exposes user names; off by default.
     *
     * @param mixed $provider WP_Sitemaps_Provider instance.
     * @return mixed The provider, or false to drop it.
     */
    public function provider(mixed $provider, string $name): mixed
    {
        if ($name !== 'users' || ! $this->seo->ownership()->coreOwns(Signal::Sitemap)) {
            return $provider;
        }

        return $this->seo->config()->bool('sitemap.users') ? $provider : false;
    }

    /**
     * @param array<string, \WP_Post_Type> $types
     * @return array<string, \WP_Post_Type>
     */
    public function postTypes(array $types): array
    {
        return $this->without($types, $this->seo->config()->list('indexability.post_types'));
    }

    /**
     * @param array<string, \WP_Taxonomy> $taxonomies
     * @return array<string, \WP_Taxonomy>
     */
    public function taxonomies(array $taxonomies): array
    {
        return $this->without($taxonomies, $this->seo->config()->list('indexability.taxonomies'));
    }

    /**
     * @template T
     * @param array<string, T> $items
     * @param string[] $excluded
     * @return array<string, T>
     */
    private function without(array $items, array $excluded): array
    {
        if (
            $excluded === []
            || ! $this->seo->config()->bool('sitemap.exclude_noindex')
            || ! $this->seo->ownership()->coreOwns(Signal::Sitemap)
        ) {
            return $items;
        }

        foreach ($excluded as $name) {
            unset($items[$name]);
        }

        return $items;
    }
}
