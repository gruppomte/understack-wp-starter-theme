<?php

declare(strict_types=1);

namespace Understack\Seo\Diagnostics;

use Understack\Core\Theme;
use Understack\Seo\Seo;

/**
 * Adds the diagnostics to Site Health → Info (administrators only, nothing public).
 */
final class SiteHealth
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function register(): void
    {
        add_filter('debug_information', [$this, 'section']);
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    public function section(array $info): array
    {
        $report = (new Report($this->seo))->build();
        $fields = [];
        $field = static function (string $key, string $label, string $value) use (&$fields): void {
            $fields[$key] = ['label' => $label, 'value' => $value, 'private' => false];
        };

        $field(
            'environment',
            __('Environment', 'understack-wp-starter'),
            sprintf(
                '%s (%s)',
                $report['environment']['type'],
                $report['environment']['declared']
                    ? __('declared', 'understack-wp-starter')
                    : __('not declared, assumed', 'understack-wp-starter')
            )
        );
        $field(
            'indexability',
            __('Indexability policy', 'understack-wp-starter'),
            $report['environment']['noindex']
                ? __('noindex, nofollow on every page (non-production environment)', 'understack-wp-starter')
                : __('indexable', 'understack-wp-starter')
        );
        $field(
            'provider',
            __('SEO plugin', 'understack-wp-starter'),
            trim($report['provider']['label'] . ' ' . $report['provider']['version'])
        );

        foreach ($report['ownership'] as $signal => $owner) {
            /* translators: %s: name of the SEO signal (title, canonical, ...) */
            $field('owner_' . $signal, sprintf(__('Owner of %s', 'understack-wp-starter'), $signal), $owner);
        }

        $field(
            'sitemap',
            __('XML sitemap', 'understack-wp-starter'),
            $report['sitemap']['url'] !== '' ? $report['sitemap']['url'] : $report['sitemap']['owner']
        );
        $field(
            'warnings',
            __('Warnings', 'understack-wp-starter'),
            $report['warnings'] === [] ? __('none', 'understack-wp-starter') : implode("\n", $report['warnings'])
        );

        $info[Theme::SLUG . '-seo'] = [
            'label' => __('SEO foundation', 'understack-wp-starter'),
            'description' => __(
                'Who emits each SEO signal, the environment policy and the configuration warnings.',
                'understack-wp-starter'
            ),
            'fields' => $fields,
        ];

        return $info;
    }
}
