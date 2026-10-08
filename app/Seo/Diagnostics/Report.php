<?php

declare(strict_types=1);

namespace Understack\Seo\Diagnostics;

use Understack\Seo\Provider;
use Understack\Seo\Seo;
use Understack\Seo\Signal;

/**
 * Read-only diagnostics: environment verdict, detected provider, ownership of
 * each signal, sitemap owner and configuration issues.
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
        $config = $this->seo->config();
        $provider = $this->seo->provider();
        $environment = $this->seo->environment();
        $ownership = $this->seo->ownership();
        $warnings = $environment->warnings;
        $detected = Provider::detect('defined');

        if ((int) get_option('blog_public') === 0) {
            $warnings[] = 'Search engine visibility is discouraged (blog_public = 0): WordPress adds noindex, '
                . 'nofollow to every page.';
        }

        if ($config->string('provider') !== 'auto' && $detected !== $provider) {
            $warnings[] = sprintf(
                'The provider is forced to "%s" while the detected plugin is "%s".',
                $provider->label(),
                $detected->label()
            );
        }

        if ($provider === Provider::RankMath && ! self::rankMathFrontEndEnabled()) {
            $warnings[] = 'Rank Math is active but its setup is not complete (registration neither done nor '
                . 'skipped): it prints no SEO tags yet while the theme already leaves those signals to it.';
        }

        if ($provider !== Provider::None && ! $provider->isVerified()) {
            $warnings[] = sprintf(
                '%s is detected but its output was not verified with this theme; '
                . 'check the head report for duplicates.',
                $provider->label()
            );
        }

        if (is_file(ABSPATH . 'robots.txt')) {
            $warnings[] = 'A physical robots.txt exists in the WordPress root: it replaces the virtual file and '
                . 'any rule added by the theme or a plugin.';
        }

        foreach ($config->issues() as $issue) {
            $warnings[] = 'Configuration: ' . $issue;
        }

        $identity = $this->seo->identity();

        foreach ($identity->issues as $issue) {
            $warnings[] = 'Organisation identity: ' . $issue;
        }

        if (! $identity->isConfigured() && $ownership->themeOwns(Signal::StructuredData)) {
            $warnings[] = 'No organisation identity is configured (Site Settings → Organization): the structured data '
                . 'has a WebSite node but no Organization node and no publisher.';
        }

        foreach (['about_page' => 'AboutPage', 'contact_page' => 'ContactPage'] as $key => $type) {
            $id = $config->int('structured_data.' . $key);

            if ($id > 0 && get_post_status($id) !== 'publish') {
                $warnings[] = sprintf(
                    'structured_data.%s points to page %d, which is not a published page.',
                    $key,
                    $id
                );
            } elseif ($id > 0 && get_post_type($id) !== 'page') {
                $warnings[] = sprintf('structured_data.%s points to %d, which is not a page (%s).', $key, $id, $type);
            }
        }

        $constant = $provider->constant();

        return [
            'environment' => $environment->toArray() + [
                'blog_public' => (int) get_option('blog_public'),
                'home' => home_url('/'),
            ],
            'provider' => [
                'id' => $provider->value,
                'label' => $provider->label(),
                'version' => $constant !== null && defined($constant) ? (string) constant($constant) : '',
                'forced' => $config->string('provider') !== 'auto',
                'verified' => $provider->isVerified(),
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
            ],
            'structured_data' => [
                'owner' => $ownership->of(Signal::StructuredData)->value,
                'about_page' => $config->int('structured_data.about_page'),
                'contact_page' => $config->int('structured_data.contact_page'),
                'author_profiles' => $config->bool('structured_data.author_profiles'),
            ],
            'breadcrumbs' => [
                'enabled' => $config->bool('breadcrumbs.enabled'),
                'schema_owner' => $ownership->of(Signal::Breadcrumbs)->value,
            ],
            'config' => $config->all(),
            'issues' => $config->issues(),
            'warnings' => $warnings,
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
