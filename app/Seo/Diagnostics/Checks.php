<?php

declare(strict_types=1);

namespace Understack\Seo\Diagnostics;

/**
 * Turns the facts gathered by Report into checks with a status. Pure, so the
 * rules are unit-tested; nothing here calls WordPress or the network.
 */
final class Checks
{
    /**
     * @param array<string, mixed> $facts See Report::facts().
     * @return array<int, array{id: string, label: string, status: string, detail: string}>
     */
    public static function evaluate(array $facts): array
    {
        $checks = [];
        $add = static function (string $id, string $label, Status $status, string $detail) use (&$checks): void {
            $checks[] = ['id' => $id, 'label' => $label, 'status' => $status->value, 'detail' => $detail];
        };

        self::environment($facts, $add);
        self::provider($facts, $add);
        self::robots($facts, $add);
        self::content($facts, $add);
        self::integrations($facts, $add);
        self::external($add);

        return $checks;
    }

    /**
     * @param array<int, array{id: string, label: string, status: string, detail: string}> $checks
     * @return array<string, int>
     */
    public static function summary(array $checks): array
    {
        $summary = [];

        foreach (Status::cases() as $status) {
            $summary[$status->value] = 0;
        }

        foreach ($checks as $check) {
            $summary[$check['status']] = ($summary[$check['status']] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $facts
     * @param callable(string, string, Status, string): void $add
     */
    private static function environment(array $facts, callable $add): void
    {
        $environment = $facts['environment'];
        $type = (string) $environment['type'];
        $production = ! in_array($type, ['local', 'development', 'staging'], true);

        $add(
            'environment',
            'Environment type',
            $environment['declared'] ? Status::Pass : Status::Warning,
            $environment['declared']
                ? sprintf('WP_ENVIRONMENT_TYPE is "%s".', $type)
                : 'WP_ENVIRONMENT_TYPE is not declared; WordPress assumes production. Declare it on every copy.'
        );

        if ($environment['warnings'] !== []) {
            $add('indexability', 'Indexability policy', Status::Warning, implode(' ', $environment['warnings']));
        } else {
            $add(
                'indexability',
                'Indexability policy',
                Status::Pass,
                $environment['noindex']
                    ? 'Non-production copy: noindex, nofollow as meta directive and X-Robots-Tag header.'
                    : 'Production: pages are indexable; no environment directive is added.'
            );
        }

        if ((int) $facts['blog_public'] === 0) {
            $add(
                'visibility',
                'Search engine visibility',
                $production ? Status::Fail : Status::Pass,
                $production
                    ? 'Settings → Reading discourages search engines on a production environment: '
                        . 'every page is noindex, nofollow.'
                    : 'Search engines are discouraged on this non-production copy (second safety net).'
            );
        } else {
            $add('visibility', 'Search engine visibility', Status::Pass, 'Search engines are allowed by WordPress.');
        }
    }

    /**
     * @param array<string, mixed> $facts
     * @param callable(string, string, Status, string): void $add
     */
    private static function provider(array $facts, callable $add): void
    {
        $provider = $facts['provider'];

        if ($provider['id'] === 'none') {
            $add('provider', 'SEO plugin', Status::Pass, 'No SEO plugin detected: the theme fallbacks are active.');
        } elseif ($provider['forced'] && $provider['detected_id'] !== $provider['id']) {
            $add('provider', 'SEO plugin', Status::Warning, sprintf(
                'The provider is forced to "%s" while the detected plugin is "%s".',
                $provider['label'],
                $provider['detected_label']
            ));
        } elseif (! $provider['verified']) {
            $add('provider', 'SEO plugin', Status::Warning, sprintf(
                '%s is detected but its output was not measured with this theme; check the head report.',
                $provider['label']
            ));
        } elseif ($provider['dormant']) {
            $add('provider', 'SEO plugin', Status::Warning, sprintf(
                '%s is active but its setup is not complete: it prints no SEO tags yet.',
                $provider['label']
            ));
        } else {
            $add('provider', 'SEO plugin', Status::Pass, sprintf(
                '%s %s owns the signals it supports; the theme stays silent for them.',
                $provider['label'],
                $provider['version']
            ));
        }

        $owners = [];

        foreach ($facts['ownership'] as $signal => $owner) {
            $owners[] = $signal . ': ' . $owner;
        }

        $add('ownership', 'Signal ownership', Status::Pass, implode(', ', $owners));
    }

    /**
     * @param array<string, mixed> $facts
     * @param callable(string, string, Status, string): void $add
     */
    private static function robots(array $facts, callable $add): void
    {
        $provider = $facts['provider'];

        $add(
            'robots_directives',
            'Robots meta and header',
            Status::Pass,
            'The theme sends the same directives as meta tag and X-Robots-Tag header; nothing else is added.'
        );
        $add(
            'robots_headers_external',
            'Headers from the server or other plugins',
            Status::NotVerified,
            'X-Robots-Tag headers added by the web server, a CDN or another plugin cannot be measured '
                . 'from the theme.'
        );

        if ($facts['robots_txt_file']) {
            $detail = 'A physical robots.txt exists in the WordPress root and replaces the virtual file '
                . 'and every rule below.';
        } else {
            $detail = 'The virtual robots.txt is served by WordPress'
                . ($provider['id'] === 'none' ? '.' : ' with the SEO plugin\'s body.');
        }

        $add('robots_txt', 'robots.txt', $facts['robots_txt_file'] ? Status::Warning : Status::Pass, $detail);
        $add(
            'robots_txt_served',
            'robots.txt as served to crawlers',
            Status::NotVerified,
            'Web server rewrites, CDN rules and WAF bot management are outside the theme; '
                . 'fetch /robots.txt from outside.'
        );

        $crawlers = $facts['crawlers'];

        if ($crawlers['policy'] === 'custom' && $crawlers['invalid']) {
            $add(
                'crawler_policy',
                'Crawler policy',
                Status::Fail,
                'crawlers.groups is invalid; nothing is appended to robots.txt.'
            );
        } elseif ($crawlers['policy'] === 'custom') {
            $add('crawler_policy', 'Crawler policy', Status::Pass, sprintf(
                '%d group(s) appended to robots.txt for: %s.',
                $crawlers['groups'],
                implode(', ', $crawlers['agents'])
            ));
        } elseif ($crawlers['policy'] === 'open') {
            $add(
                'crawler_policy',
                'Crawler policy',
                Status::Pass,
                'The site owner chose to leave robots.txt open to every crawler, AI training crawlers included.'
            );
        } else {
            $add(
                'crawler_policy',
                'Crawler policy',
                Status::Warning,
                'No decision recorded (crawlers.policy = unset): every crawler, AI training crawlers included, '
                    . 'follows the default robots.txt. Set "open" or "custom" in config/seo.php.'
            );
        }

        $add(
            'ai_fetchers',
            'AI assistants fetching on a user\'s request',
            Status::NotVerified,
            'User-initiated fetchers are documented as not bound by robots.txt; '
                . 'only server-side access control applies.'
        );

        $sitemap = $facts['sitemap'];

        if ($sitemap['owner'] === 'provider') {
            $add(
                'sitemap',
                'XML sitemap',
                Status::Pass,
                'Managed by the SEO plugin; submit the plugin\'s sitemap index.'
            );
        } elseif ($sitemap['owner'] === 'disabled') {
            $add('sitemap', 'XML sitemap', Status::Warning, 'The core sitemap is disabled and no plugin provides one.');
        } else {
            $add('sitemap', 'XML sitemap', Status::Pass, 'Core sitemap at ' . $sitemap['url'] . '.');
        }
    }

    /**
     * @param array<string, mixed> $facts
     * @param callable(string, string, Status, string): void $add
     */
    private static function content(array $facts, callable $add): void
    {
        $identity = $facts['identity'];
        $owner = (string) $facts['ownership']['structured_data'];

        if ($owner === 'provider') {
            $add('structured_data', 'Structured data', Status::Pass, 'The SEO plugin owns the JSON-LD graph.');
        } elseif ($owner === 'theme') {
            $add(
                'structured_data',
                'Structured data',
                $identity['configured'] ? Status::Pass : Status::Warning,
                $identity['configured']
                    ? sprintf('Theme graph with %s "%s".', $identity['schema_type'], $identity['name'])
                    : 'Theme graph without an Organization node: configure Site Settings → Organization.'
            );
        } else {
            $add('structured_data', 'Structured data', Status::Warning, 'No JSON-LD graph is emitted.');
        }

        if ($identity['issues'] !== []) {
            $add('identity', 'Organisation identity', Status::Warning, implode(' ', $identity['issues']));
        } elseif ($identity['configured']) {
            $add('identity', 'Organisation identity', Status::Pass, 'Every configured value is valid.');
        } else {
            $add('identity', 'Organisation identity', Status::Warning, 'Not configured.');
        }

        $add(
            'page_types',
            'About and Contact pages',
            $facts['page_type_issues'] === [] ? Status::Pass : Status::Fail,
            $facts['page_type_issues'] === []
                ? 'Configured page IDs are published pages (or none are configured).'
                : implode(' ', $facts['page_type_issues'])
        );
        $add(
            'configuration',
            'Configuration',
            $facts['config_issues'] === [] ? Status::Pass : Status::Warning,
            $facts['config_issues'] === [] ? 'config/seo.php is valid.' : implode(' ', $facts['config_issues'])
        );
        $add(
            'breadcrumbs',
            'Breadcrumbs',
            Status::Pass,
            $facts['breadcrumbs_enabled'] ? 'Enabled: visible trail and BreadcrumbList node.' : 'Disabled (default).'
        );
    }

    /**
     * @param array<string, mixed> $facts
     * @param callable(string, string, Status, string): void $add
     */
    private static function integrations(array $facts, callable $add): void
    {
        $wpml = $facts['wpml'];
        $add(
            'wpml',
            'WPML',
            $wpml['active'] ? Status::NotVerified : Status::Pass,
            $wpml['active']
                ? sprintf(
                    'WPML %s detected: hreflang is left to WPML, URLs come from language-aware functions, '
                    . 'About/Contact page IDs are mapped to the current language. '
                    . 'Compatibility is designed, not certified.',
                    $wpml['version']
                )
                : 'Not installed: nothing to do.'
        );

        $woocommerce = $facts['woocommerce'];
        $add(
            'woocommerce',
            'WooCommerce',
            Status::Pass,
            $woocommerce['active']
                ? sprintf(
                    'WooCommerce %s detected: Product and Offer data stay with WooCommerce or the SEO plugin; '
                    . 'the theme emits no graph and no breadcrumbs on shop pages and nothing on cart, '
                    . 'checkout and account pages.',
                    $woocommerce['version']
                )
                : 'Not installed: nothing to do.'
        );
    }

    /**
     * @param callable(string, string, Status, string): void $add
     */
    private static function external(callable $add): void
    {
        $add(
            'html_semantics',
            'Rendered HTML (headings, landmarks, links, images)',
            Status::NotVerified,
            'Not inspected from inside WordPress; run "node tests/seo-report.mjs --url …" '
                . 'or the end-to-end suite.'
        );
        $add(
            'search_engines',
            'Indexing status at search engines',
            Status::NotVerified,
            'Requires Search Console, Bing Webmaster Tools or the engines\' own tools.'
        );
        $add(
            'cdn_waf',
            'CDN and firewall bot rules',
            Status::NotVerified,
            'Edge rules can block or allow crawlers independently of robots.txt; '
                . 'check the hosting or CDN panel.'
        );
    }
}
