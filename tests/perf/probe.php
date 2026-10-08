<?php

/**
 * Temporary measurement probe for the performance audit (docs/seo-geo/13-release-candidate.md).
 *
 * Install as a must-use plugin on a local site only, run tests/perf/measure.mjs,
 * then delete it. It acts only on requests carrying the "__perf" query variable
 * and appends an HTML comment with the measurements to the footer:
 * total time, database queries and duplicates, peak memory, hooks registered by
 * the SEO module, time of the theme's head output, time to build the JSON-LD
 * graph, and the number of HTTP API requests made during the page.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects, WordPress.Security.NonceVerification.Recommended -- local measurement probe.

if (! isset($_GET['__perf']) || PHP_SAPI === 'cli') {
    return;
}

if (! defined('SAVEQUERIES')) {
    define('SAVEQUERIES', true);
}

$GLOBALS['__perf'] = ['http' => 0, 'boot_ms' => 0.0, 'head_ms' => 0.0, 'mark' => 0.0];

add_filter('pre_http_request', static function (mixed $preempt): mixed {
    $GLOBALS['__perf']['http']++;

    return $preempt;
}, 1);

add_action('after_setup_theme', static function (): void {
    $GLOBALS['__perf']['mark'] = microtime(true);
}, 19);

add_action('after_setup_theme', static function (): void {
    $GLOBALS['__perf']['boot_ms'] = (microtime(true) - $GLOBALS['__perf']['mark']) * 1000;
}, 21);

add_action('wp_head', static function (): void {
    $GLOBALS['__perf']['mark'] = microtime(true);
}, 1);

add_action('wp_head', static function (): void {
    $GLOBALS['__perf']['head_ms'] = (microtime(true) - $GLOBALS['__perf']['mark']) * 1000;
}, 3);

add_action('wp_footer', static function (): void {
    global $wpdb, $wp_filter;

    $sql = [];

    foreach ((array) ($wpdb->queries ?? []) as $query) {
        $sql[] = is_array($query) ? (string) $query[0] : '';
    }

    $duplicates = 0;

    foreach (array_count_values($sql) as $count) {
        $duplicates += max(0, $count - 1);
    }

    $seoHooks = 0;

    foreach ($wp_filter as $hook) {
        foreach ($hook->callbacks as $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'] ?? null;
                $owner = is_array($function) ? $function[0] : $function;
                $class = is_object($owner) ? get_class($owner) : (is_string($owner) ? $owner : '');

                if (str_starts_with($class, 'Understack\\Seo')) {
                    $seoHooks++;
                }
            }
        }
    }

    $jsonld = null;

    if (class_exists('Understack\\Seo\\Seo') && Understack\Seo\Seo::current() !== null) {
        $seo = Understack\Seo\Seo::current();
        $start = microtime(true);

        for ($run = 0; $run < 5; $run++) {
            Understack\Seo\StructuredData\Serializer::encode(
                Understack\Seo\StructuredData\Graph::build(
                    (new Understack\Seo\StructuredData\Collector($seo))->collect()
                )
            );
        }

        $jsonld = (microtime(true) - $start) * 1000 / 5;
    }

    $metrics = [
        'total_ms' => round((microtime(true) - (float) ($GLOBALS['timestart'] ?? microtime(true))) * 1000, 2),
        'queries' => get_num_queries(),
        'duplicate_queries' => $duplicates,
        'peak_memory_kb' => (int) round(memory_get_peak_usage(false) / 1024),
        'seo_hooks' => $seoHooks,
        'boot_ms' => round($GLOBALS['__perf']['boot_ms'], 3),
        'head_ms' => round($GLOBALS['__perf']['head_ms'], 3),
        'jsonld_ms' => $jsonld === null ? null : round($jsonld, 3),
        'http_requests' => $GLOBALS['__perf']['http'],
    ];

    $mode = isset($_GET['__perf']) ? sanitize_text_field(wp_unslash((string) $_GET['__perf'])) : '';

    if ($mode === 'queries') {
        $metrics['sql'] = array_map(static fn (string $query): string => substr($query, 0, 160), $sql);
    }

    echo "\n<!--__perf " . wp_json_encode($metrics) . " -->\n";
}, 999);
