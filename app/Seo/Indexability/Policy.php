<?php

declare(strict_types=1);

namespace Understack\Seo\Indexability;

use Understack\Seo\Config;
use Understack\Seo\PageContext;
use Understack\Seo\PageKind;
use Understack\Seo\Seo;
use Understack\Seo\Signal;

/**
 * Robots directives. The theme never prints the robots meta tag: it feeds
 * core's wp_robots filter (also used by Yoast) and Rank Math's own filter,
 * because Rank Math clears the wp_robots chain and prints its own tag.
 * Page-type directives apply only while core owns the tag; the environment
 * policy applies with every provider, as a meta directive and as a header.
 */
final class Policy
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function register(): void
    {
        add_filter('wp_robots', [$this, 'robots'], PHP_INT_MAX);
        add_filter('rank_math/frontend/robots', [$this, 'rankMathRobots']);
        add_filter('wp_headers', [$this, 'headers']);
    }

    /**
     * @param array<string, bool|string> $robots
     * @return array<string, bool|string>
     */
    public function robots(array $robots): array
    {
        if (! $this->seo->isFrontEnd()) {
            return $robots;
        }

        if ($this->seo->ownership()->coreOwns(Signal::Robots)) {
            $robots = self::withPageDirectives($robots, $this->seo->context(), $this->seo->config());
        }

        if ($this->seo->environment()->noindex) {
            $robots = self::forceNoindex($robots);
        }

        return $robots;
    }

    /**
     * Rank Math format: ['index' => 'noindex', 'follow' => 'nofollow', ...].
     *
     * @param array<string, string> $robots
     * @return array<string, string>
     */
    public function rankMathRobots(array $robots): array
    {
        if (! $this->seo->environment()->noindex) {
            return $robots;
        }

        unset($robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview']);
        $robots['index'] = 'noindex';
        $robots['follow'] = 'nofollow';

        return $robots;
    }

    /**
     * X-Robots-Tag on every response WordPress serves while the site is not production.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function headers(array $headers): array
    {
        if ($this->seo->config()->bool('indexability.x_robots_tag') && $this->seo->environment()->noindex) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }

        return $headers;
    }

    /**
     * Replaces index/follow and the snippet limits with noindex, nofollow,
     * the same output core produces for a non-public site.
     *
     * @param array<string, bool|string> $robots
     * @return array<string, bool|string>
     */
    public static function forceNoindex(array $robots): array
    {
        unset(
            $robots['index'],
            $robots['follow'],
            $robots['max-snippet'],
            $robots['max-image-preview'],
            $robots['max-video-preview']
        );
        $robots['noindex'] = true;
        $robots['nofollow'] = true;

        return $robots;
    }

    /**
     * Directives for page types core leaves indexable: 404 pages, attachment
     * pages and the post types or taxonomies listed in the configuration.
     *
     * @param array<string, bool|string> $robots
     * @return array<string, bool|string>
     */
    public static function withPageDirectives(array $robots, PageContext $context, Config $config): array
    {
        $noindex = match (true) {
            $context->kind === PageKind::NotFound => $config->string('indexability.not_found') === 'noindex',
            $context->isAttachment() => $config->string('indexability.attachments') === 'noindex',
            $context->isSingular(), $context->kind === PageKind::PostTypeArchive
                => in_array($context->postType, $config->list('indexability.post_types'), true),
            $context->kind === PageKind::Term
                => in_array($context->postType, $config->list('indexability.taxonomies'), true),
            default => false,
        };

        if ($noindex) {
            unset($robots['index']);
            $robots['noindex'] = true;
            $robots['follow'] = $robots['follow'] ?? true;
        }

        return $robots;
    }
}
