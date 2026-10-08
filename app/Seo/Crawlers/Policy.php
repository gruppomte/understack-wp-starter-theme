<?php

declare(strict_types=1);

namespace Understack\Seo\Crawlers;

use Understack\Seo\Seo;

/**
 * Appends the configured robots.txt groups to the virtual robots.txt, after
 * the block WordPress core (or the SEO plugin) produces. Nothing is appended
 * unless the site owner set crawlers.policy to "custom" with valid groups.
 * A physical robots.txt in the web root bypasses this entirely.
 */
final class Policy
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function register(): void
    {
        add_filter('robots_txt', [$this, 'append'], 20);
    }

    /**
     * @param mixed $output Current robots.txt body.
     */
    public function append(mixed $output): mixed
    {
        if (! is_string($output)) {
            return $output;
        }

        $groups = $this->groups();

        if ($groups === []) {
            return $output;
        }

        return rtrim($output) . "\n\n" . Groups::render($groups) . "\n";
    }

    /**
     * The validated groups when the policy is custom, else nothing.
     *
     * @return array<int, array{agents: string[], allow: string[], disallow: string[], crawl_delay: int|null}>
     */
    public function groups(): array
    {
        $config = $this->seo->config();

        if ($config->string('crawlers.policy') !== 'custom') {
            return [];
        }

        return Groups::normalise($config->get('crawlers.groups')) ?? [];
    }
}
