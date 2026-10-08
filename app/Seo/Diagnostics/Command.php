<?php

declare(strict_types=1);

namespace Understack\Seo\Diagnostics;

use Understack\Core\Theme;
use Understack\Seo\Seo;
use WP_CLI;

/**
 * WP-CLI: `wp <theme slug> seo report [--format=json]`. Exits with 1 when a check fails.
 */
final class Command
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function register(): void
    {
        WP_CLI::add_command(Theme::SLUG . ' seo', $this, [
            'shortdesc' => 'SEO foundation diagnostics for this site.',
        ]);
    }

    /**
     * Runs the SEO checks (PASS, WARNING, FAIL, NOT VERIFIED) and shows the measured facts.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * @param string[] $args
     * @param array<string, string> $assoc
     */
    public function report(array $args, array $assoc): void
    {
        $report = (new Report($this->seo))->build();

        if (($assoc['format'] ?? 'table') === 'json') {
            WP_CLI::line((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        \WP_CLI\Utils\format_items('table', $report['checks'], ['status', 'label', 'detail']);

        $rows = [];

        foreach (self::flatten($report) as $setting => $value) {
            $skip = str_starts_with($setting, 'checks') || str_starts_with($setting, 'config.');

            if ($skip || $setting === 'warnings') {
                continue;
            }

            $rows[] = ['setting' => $setting, 'value' => $value];
        }

        \WP_CLI\Utils\format_items('table', $rows, ['setting', 'value']);

        $summary = $report['summary'];
        WP_CLI::line(sprintf(
            'Checks: %d passed, %d warning(s), %d failed, %d not verified.',
            $summary[Status::Pass->value] ?? 0,
            $summary[Status::Warning->value] ?? 0,
            $summary[Status::Fail->value] ?? 0,
            $summary[Status::NotVerified->value] ?? 0
        ));

        if (($summary[Status::Fail->value] ?? 0) > 0) {
            WP_CLI::warning('At least one check failed.');
            WP_CLI::halt(1);
        }

        WP_CLI::success('No failed checks.');
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private static function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value) && ! array_is_list($value)) {
                $flat += self::flatten($value, $path);
                continue;
            }

            $flat[$path] = self::stringify($value);
        }

        return $flat;
    }

    private static function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return implode(', ', array_map([self::class, 'stringify'], $value));
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
