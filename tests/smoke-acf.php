<?php

/**
 * ACF part of the smoke test, included by smoke.php when ACF is loaded.
 * Uses $check and $themeDirectory from the including scope.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || ! isset($check, $themeDirectory) || ! is_callable($check) || ! is_string($themeDirectory)) {
    exit;
}

$source = str_starts_with((string) acf_get_setting('path'), $themeDirectory) ? 'bundled' : 'plugin';
$groups = count(acf_get_local_field_groups());
$check(
    'ACF loaded',
    defined('ACF_VERSION'),
    sprintf('ACF %s (%s%s)', (string) constant('ACF_VERSION'), defined('ACF_PRO') ? 'PRO, ' : '', $source)
);
$check('ACF JSON load path', in_array($themeDirectory . '/acf-json', (array) acf_get_setting('load_json'), true));
$check('ACF JSON save path', acf_get_setting('save_json') === $themeDirectory . '/acf-json');
$check('ACF local field groups', $groups > 0, $groups . ' group(s)');
$check(
    'ACF options page',
    function_exists('acf_get_options_page') && acf_get_options_page('site-settings') !== null
);
