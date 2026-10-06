<?php

/**
 * ACF Local JSON status and sync. Field groups always render from acf-json/;
 * syncing only updates the database copy used by the field group UI (local,
 * staging), mirroring the "Sync available" action of the ACF admin.
 *
 *   wp eval-file scripts/acf-json.php          status: groups whose JSON is newer than the database
 *   wp eval-file scripts/acf-json.php sync     import those groups into the database
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

/** @var string[] $args Positional arguments passed by "wp eval-file". */
$args = isset($args) && is_array($args) ? $args : [];
$sync = in_array('sync', $args, true);

if (! function_exists('acf_get_local_json_files')) {
    fwrite(STDERR, "ACF is not loaded.\n");
    exit(1);
}

$files = acf_get_local_json_files('acf-field-group');
$pending = [];

foreach (acf_get_internal_post_type_posts('acf-field-group') as $group) {
    if (! empty($group['private']) || ($group['local'] ?? '') !== 'json') {
        continue;
    }

    $key = (string) $group['key'];
    $modified = (int) ($group['modified'] ?? 0);

    if (empty($group['ID'])) {
        $pending[$key] = ['group' => $group, 'reason' => 'not in the database'];
    } elseif ($modified > 0 && $modified > (int) get_post_modified_time('U', true, (int) $group['ID'])) {
        $pending[$key] = ['group' => $group, 'reason' => 'JSON newer than the database'];
    }
}

if ($pending === []) {
    fwrite(STDOUT, sprintf("ACF JSON in sync (%d file(s)).\n", count($files)));
    exit(0);
}

foreach ($pending as $key => $item) {
    fwrite(STDOUT, sprintf("%s  %s  (%s)\n", $sync ? 'sync' : 'pending', $item['group']['title'], $item['reason']));
}

if (! $sync) {
    fwrite(STDOUT, "Run with \"sync\" to import them.\n");
    exit(0);
}

// Keep the JSON files untouched while importing.
acf_update_setting('json', false);

foreach ($pending as $key => $item) {
    if (! isset($files[$key])) {
        continue;
    }

    $local = json_decode((string) file_get_contents($files[$key]), true);

    if (! is_array($local)) {
        fwrite(STDERR, sprintf("Could not read %s.\n", $files[$key]));
        exit(1);
    }

    $local['ID'] = (int) ($item['group']['ID'] ?? 0);
    acf_import_internal_post_type($local, 'acf-field-group');
}

fwrite(STDOUT, sprintf("%d field group(s) imported.\n", count($pending)));
