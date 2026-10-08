<?php

declare(strict_types=1);

namespace Understack\Seo\Diagnostics;

use Understack\Seo\Crawlers\Groups;
use Understack\Seo\Integrations\WooCommerce;
use Understack\Seo\Integrations\Wpml;
use Understack\Seo\Provider;
use Understack\Seo\Seo;
use Understack\Seo\Signal;

/**
 * Read-only diagnostics: the facts of the current site and the checks
 * derived from them (PASS, WARNING, FAIL, NOT VERIFIED). No network access.
 */
final class Report
{
    public function __construct(private readonly Seo $seo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $facts = $this->facts();
        $checks = Checks::evaluate($facts);
        $warnings = [];

        foreach ($checks as $check) {
            if (in_array($check['status'], [Status::Warning->value, Status::Fail->value], true)) {
                $warnings[] = $check['label'] . ': ' . $check['detail'];
            }
        }

        return $facts + [
            'checks' => $checks,
            'summary' => Checks::summary($checks),
            'warnings' => $warnings,
            'config' => $this->seo->config()->all(),
        ];
    }

    /**
     * Everything the checks need, measured from inside WordPress.
     *
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        $config = $this->seo->config();
        $provider = $this->seo->provider();
        $detected = Provider::detect('defined');
        $environment = $this->seo->environment();
        $ownership = $this->seo->ownership();
        $identity = $this->seo->identity();
        $constant = $provider->constant();
        $groups = $config->string('crawlers.policy') === 'custom'
            ? Groups::normalise($config->get('crawlers.groups'))
            : [];

        return [
            'environment' => $environment->toArray() + ['home' => home_url('/')],
            'blog_public' => (int) get_option('blog_public'),
            'provider' => [
                'id' => $provider->value,
                'label' => $provider->label(),
                'version' => $constant !== null && defined($constant) ? (string) constant($constant) : '',
                'forced' => $config->string('provider') !== 'auto',
                'detected_id' => $detected->value,
                'detected_label' => $detected->label(),
                'verified' => $provider->isVerified(),
                'dormant' => $provider === Provider::RankMath && ! self::rankMathFrontEndEnabled(),
            ],
            'ownership' => $ownership->toArray(),
            'sitemap' => $this->sitemap(),
            'identity' => [
                'configured' => $identity->isConfigured(),
                'type' => $identity->type,
                'schema_type' => $identity->schemaType(),
                'name' => $identity->name,
                'logo' => $identity->logo !== [],
                'address' => $identity->hasPostalAddress()
                    ? 'complete'
                    : ($identity->hasAnyAddress() ? 'partial' : 'none'),
                'same_as' => count($identity->sameAs),
                'issues' => $identity->issues,
            ],
            'structured_data' => [
                'owner' => $ownership->of(Signal::StructuredData)->value,
                'about_page' => $config->int('structured_data.about_page'),
                'contact_page' => $config->int('structured_data.contact_page'),
                'author_profiles' => $config->bool('structured_data.author_profiles'),
            ],
            'page_type_issues' => $this->pageTypeIssues(),
            'breadcrumbs_enabled' => $config->bool('breadcrumbs.enabled'),
            'crawlers' => [
                'policy' => $config->string('crawlers.policy'),
                'invalid' => $config->string('crawlers.policy') === 'custom' && $groups === null,
                'groups' => count($groups ?? []),
                'agents' => Groups::agents($groups ?? []),
            ],
            'robots_txt_file' => is_file(ABSPATH . 'robots.txt'),
            'config_issues' => $config->issues(),
            'wpml' => Wpml::facts(),
            'woocommerce' => WooCommerce::facts(),
        ];
    }

    /**
     * @return array{owner: string, url: string}
     */
    private function sitemap(): array
    {
        $owner = $this->seo->ownership()->of(Signal::Sitemap)->value;

        if (! $this->seo->ownership()->coreOwns(Signal::Sitemap)) {
            return ['owner' => $owner, 'url' => ''];
        }

        $server = function_exists('wp_sitemaps_get_server') ? wp_sitemaps_get_server() : null;

        if ($server === null || ! $server->sitemaps_enabled()) {
            return ['owner' => 'disabled', 'url' => ''];
        }

        return ['owner' => $owner, 'url' => home_url('/wp-sitemap.xml')];
    }

    /**
     * @return string[]
     */
    private function pageTypeIssues(): array
    {
        $issues = [];

        foreach (['about_page' => 'AboutPage', 'contact_page' => 'ContactPage'] as $key => $type) {
            $id = $this->seo->config()->int('structured_data.' . $key);

            if ($id <= 0) {
                continue;
            }

            if (get_post_status($id) !== 'publish') {
                $issues[] = sprintf(
                    'structured_data.%s points to %d, which is not a published page (%s).',
                    $key,
                    $id,
                    $type
                );
            } elseif (get_post_type($id) !== 'page') {
                $issues[] = sprintf('structured_data.%s points to %d, which is not a page (%s).', $key, $id, $type);
            }
        }

        return $issues;
    }

    /**
     * Mirrors Rank Math's registration gate (constant, option or connected
     * account) without touching it; best effort, read-only.
     */
    private static function rankMathFrontEndEnabled(): bool
    {
        if (defined('RANK_MATH_REGISTRATION_SKIP') && constant('RANK_MATH_REGISTRATION_SKIP')) {
            return true;
        }

        if ((bool) get_option('rank_math_registration_skip')) {
            return true;
        }

        $data = get_option('rank_math_connect_data');

        return is_array($data) && ! empty($data['connected']) && ! empty($data['api_key']);
    }
}
