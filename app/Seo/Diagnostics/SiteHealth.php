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

        foreach ($report['checks'] as $check) {
            $fields['check_' . $check['id']] = [
                'label' => $check['label'],
                'value' => $check['status'] . ' — ' . $check['detail'],
                'private' => false,
            ];
        }

        $summary = $report['summary'];
        $fields['summary'] = [
            'label' => __('Summary', 'understack-wp-starter'),
            'value' => sprintf(
                /* translators: 1: passed, 2: warnings, 3: failed, 4: not verified */
                __('%1$d passed, %2$d warning(s), %3$d failed, %4$d not verified', 'understack-wp-starter'),
                $summary[Status::Pass->value] ?? 0,
                $summary[Status::Warning->value] ?? 0,
                $summary[Status::Fail->value] ?? 0,
                $summary[Status::NotVerified->value] ?? 0
            ),
            'private' => false,
        ];

        $info[Theme::SLUG . '-seo'] = [
            'label' => __('SEO foundation', 'understack-wp-starter'),
            'description' => __(
                'Checks of the SEO foundation. NOT VERIFIED marks what cannot be measured from inside WordPress.',
                'understack-wp-starter'
            ),
            'fields' => $fields,
        ];

        return $info;
    }
}
