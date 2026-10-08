<?php

declare(strict_types=1);

namespace Understack\Seo\Indexability;

use Understack\Seo\Config;
use Understack\Seo\Text;

/**
 * Environment policy: non-production copies of a site are never indexable.
 * The decision relies only on explicit configuration (WP_ENVIRONMENT_TYPE and
 * the optional list of production hosts), never on domain patterns. A missing
 * or contradictory configuration never turns into noindex: it is reported.
 */
final class Environment
{
    public const NON_PRODUCTION = ['local', 'development', 'staging'];

    /**
     * Pure decision, unit-tested without WordPress.
     *
     * @param string $type WordPress environment type (production when unknown).
     * @param bool $declared Whether WP_ENVIRONMENT_TYPE was set explicitly.
     * @param string $host Host of the site's home URL.
     * @param string[] $productionHosts Optional explicit list of production hosts.
     * @param string $policy "noindex" or "off".
     */
    public static function evaluate(
        string $type,
        bool $declared,
        string $host,
        array $productionHosts,
        string $policy = 'noindex'
    ): Verdict {
        $type = strtolower(trim($type));
        $type = $type === '' ? 'production' : $type;
        $host = strtolower(trim($host));
        $hosts = array_map(static fn (string $item): string => strtolower(trim($item)), $productionHosts);
        $hostListed = $hosts !== [] && in_array($host, $hosts, true);
        $warnings = [];

        if ($policy === 'off') {
            return new Verdict(false, $type, $declared, $host, [
                'The environment policy is switched off (indexability.non_production = off).',
            ]);
        }

        if (! in_array($type, self::NON_PRODUCTION, true)) {
            if (! $declared) {
                $warnings[] = 'WP_ENVIRONMENT_TYPE is not declared, so WordPress assumes production. Declare it on '
                    . 'every copy of the site (wp-config.php or the environment) so staging copies are never '
                    . 'indexed by default.';
            }

            if ($hosts !== [] && ! $hostListed) {
                $warnings[] = sprintf(
                    'The environment is production but the host "%s" is not in indexability.production_hosts. '
                    . 'No noindex is applied; fix WP_ENVIRONMENT_TYPE or the host list.',
                    $host
                );
            }

            return new Verdict(false, $type, $declared, $host, [
                $declared
                    ? sprintf('Environment type "%s" is production: indexable.', $type)
                    : 'Environment type not declared: treated as production, indexable.',
            ], $warnings);
        }

        if ($hostListed) {
            $warnings[] = sprintf(
                'Environment type "%s" contradicts indexability.production_hosts, which lists "%s". '
                . 'No noindex is applied; fix the configuration.',
                $type,
                $host
            );

            return new Verdict(false, $type, $declared, $host, [
                'Contradictory configuration: the declared production host wins, the site stays indexable.',
            ], $warnings);
        }

        return new Verdict(true, $type, $declared, $host, [
            sprintf('Environment type "%s" is not production: noindex, nofollow on every page.', $type),
        ]);
    }

    /**
     * The verdict for the running WordPress site.
     */
    public static function current(Config $config): Verdict
    {
        $declared = (defined('WP_ENVIRONMENT_TYPE') && (string) constant('WP_ENVIRONMENT_TYPE') !== '')
            || (function_exists('getenv') && (string) getenv('WP_ENVIRONMENT_TYPE') !== '');

        return self::evaluate(
            wp_get_environment_type(),
            $declared,
            Text::host(home_url('/')),
            $config->list('indexability.production_hosts'),
            $config->string('indexability.non_production')
        );
    }
}
