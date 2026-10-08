<?php

declare(strict_types=1);

namespace Understack\Seo\Crawlers;

/**
 * robots.txt groups declared in the configuration: validation and rendering.
 * Pure: the theme ships no user-agent list; every token comes from the site
 * owner's configuration (see docs/seo-geo/05-crawler-policy.md).
 */
final class Groups
{
    public const MAX_GROUPS = 50;

    /**
     * @return array<int, array{agents: string[], allow: string[], disallow: string[], crawl_delay: int|null}>|null
     *     Null when any group is invalid.
     */
    public static function normalise(mixed $value): ?array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > self::MAX_GROUPS) {
            return null;
        }

        $groups = [];

        foreach ($value as $group) {
            if (! is_array($group)) {
                return null;
            }

            $agents = self::tokens($group['agents'] ?? ($group['agent'] ?? []));
            $allow = self::paths($group['allow'] ?? []);
            $disallow = self::paths($group['disallow'] ?? []);
            $delay = $group['crawl_delay'] ?? null;

            if ($agents === null || $agents === [] || $allow === null || $disallow === null) {
                return null;
            }

            if ($allow === [] && $disallow === []) {
                return null;
            }

            if ($delay !== null && (! is_int($delay) || $delay < 1 || $delay > 3600)) {
                return null;
            }

            $groups[] = ['agents' => $agents, 'allow' => $allow, 'disallow' => $disallow, 'crawl_delay' => $delay];
        }

        return $groups;
    }

    /**
     * @param array<int, array{agents: string[], allow: string[], disallow: string[], crawl_delay: int|null}> $groups
     */
    public static function render(array $groups): string
    {
        $blocks = [];

        foreach ($groups as $group) {
            $lines = [];

            foreach ($group['agents'] as $agent) {
                $lines[] = 'User-agent: ' . $agent;
            }

            foreach ($group['allow'] as $path) {
                $lines[] = 'Allow: ' . $path;
            }

            foreach ($group['disallow'] as $path) {
                $lines[] = 'Disallow: ' . $path;
            }

            if ($group['crawl_delay'] !== null) {
                $lines[] = 'Crawl-delay: ' . $group['crawl_delay'];
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param array<int, array{agents: string[], allow: string[], disallow: string[], crawl_delay: int|null}> $groups
     * @return string[] Every user-agent token addressed by the groups.
     */
    public static function agents(array $groups): array
    {
        $agents = [];

        foreach ($groups as $group) {
            foreach ($group['agents'] as $agent) {
                $agents[] = $agent;
            }
        }

        return array_values(array_unique($agents));
    }

    /**
     * @return string[]|null
     */
    private static function tokens(mixed $value): ?array
    {
        $value = is_string($value) ? [$value] : $value;

        if (! is_array($value)) {
            return null;
        }

        $tokens = [];

        foreach ($value as $token) {
            if (! is_string($token)) {
                return null;
            }

            $token = trim($token);

            if ($token === '' || strlen($token) > 64 || preg_match('/^[A-Za-z0-9_.*\-]+$/', $token) !== 1) {
                return null;
            }

            if (! in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * @return string[]|null
     */
    private static function paths(mixed $value): ?array
    {
        $value = is_string($value) ? [$value] : $value;

        if (! is_array($value)) {
            return null;
        }

        $paths = [];

        foreach ($value as $path) {
            if (! is_string($path)) {
                return null;
            }

            $path = trim($path);

            $valid = $path !== '' && strlen($path) <= 200
                && preg_match('#^/[A-Za-z0-9_\-./~*$?=&%:+@,;()]*$#', $path) === 1;

            if (! $valid) {
                return null;
            }

            if (! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
