<?php

/**
 * Runtime smoke test executed inside WordPress:
 *   php tests/smoke.php [/path/to/wp-load.php]
 *
 * Verifies the theme bootstrap, Timber/Twig rendering, ACF integration and the
 * Vite manifest. Exits with 1 when a check fails.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

// A failed WordPress bootstrap (for example a database error) ends the process
// with exit code 0: without this guard the result would look like a pass.
$state = new stdClass();
$state->completed = false;

register_shutdown_function(static function () use ($state): void {
    if (! $state->completed) {
        fwrite(STDERR, "Smoke test aborted before completion (WordPress did not load?).\n");
        exit(1);
    }
});

$themeDirectory = dirname(__DIR__);
$wpLoad = $argv[1] ?? null;

if ($wpLoad === null) {
    $directory = $themeDirectory;

    for ($depth = 0; $depth < 6; $depth++) {
        if (is_file($directory . '/wp-load.php')) {
            $wpLoad = $directory . '/wp-load.php';
            break;
        }

        $directory = dirname($directory);
    }
}

if ($wpLoad === null || ! is_file($wpLoad)) {
    fwrite(STDERR, "wp-load.php not found.\n");
    exit(1);
}

require_once $wpLoad;

$failures = 0;

$check = static function (string $label, bool $ok, string $detail = '') use (&$failures): void {
    if (! $ok) {
        $failures++;
    }

    $suffix = $detail !== '' ? ' - ' . $detail : '';
    fwrite(STDOUT, sprintf("[%s] %s%s\n", $ok ? ' ok ' : 'FAIL', $label, $suffix));
};

$check(
    'Theme is active',
    realpath(get_template_directory()) === realpath($themeDirectory),
    get_template()
);

if (realpath(get_template_directory()) !== realpath($themeDirectory)) {
    fwrite(STDOUT, "Activate the theme before running the smoke test.\n");
    exit(1);
}

$check('Composer autoloader', class_exists(Understack\Core\Theme::class));
$check(
    'Timber initialised',
    class_exists(Timber\Timber::class),
    'Timber ' . Timber\Timber::$version
);
$check('Twig available', class_exists(Twig\Environment::class), 'Twig ' . Twig\Environment::VERSION);
$menus = get_registered_nav_menus();
$check('Navigation menus registered', isset($menus['primary'], $menus['footer']));

$context = Timber\Timber::context();
$check('Timber context', isset($context['site'], $context['menus'], $context['options']));

$samplePage = Timber\Timber::get_post(['post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1]);
$samplePost = Timber\Timber::get_post(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1]);
$samplePosts = Timber\Timber::get_posts(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 3]);
$listing = ['description' => '', 'posts' => $samplePosts, 'pagination' => null];

$templates = [
    'system/404.twig' => $context,
    'archives/archive.twig' => array_merge($context, $listing, ['title' => 'Archive']),
    'archives/index.twig' => array_merge($context, $listing, ['title' => 'Index']),
    'archives/search.twig' => array_merge($context, $listing, ['title' => 'Search']),
];

if ($samplePage instanceof Timber\Post) {
    $pageContext = array_merge($context, ['post' => $samplePage]);
    $templates['pages/page.twig'] = $pageContext;
    $templates['pages/templates/full-width.twig'] = $pageContext;
    $templates['pages/templates/landing.twig'] = $pageContext;
    $templates['pages/front-page.twig'] = array_merge($pageContext, [
        'latest_posts' => $samplePosts,
        'posts_page_link' => home_url('/'),
    ]);
}

if ($samplePost instanceof Timber\Post) {
    $templates['singles/single.twig'] = array_merge($context, ['post' => $samplePost]);
}

foreach ($templates as $template => $data) {
    try {
        $html = (string) Timber\Timber::compile($template, $data);
        $check('Render ' . $template, str_contains($html, 'id="main-content"'), strlen($html) . ' bytes');
    } catch (Throwable $exception) {
        $check('Render ' . $template, false, $exception->getMessage());
    }
}

$manifest = Understack\Core\Assets::manifest();
$check(
    'Vite manifest',
    $manifest->exists()
        && $manifest->entry('src/js/app.js') !== null
        && $manifest->entry('src/css/app.css') !== null
);
$check('Dev server not used', Understack\Core\Assets::devServer() === null);

$editorStyles = array_filter(
    get_editor_stylesheets(),
    static fn (string $url): bool => str_contains($url, 'public/build/')
);
$check('Editor stylesheet registered', count($editorStyles) > 0);

$imageSizes = array_keys(wp_get_additional_image_sizes());
$check('Image sizes registered', in_array('card', $imageSizes, true) && in_array('wide', $imageSizes, true));

$pageTemplates = array_keys(wp_get_theme()->get_page_templates(null, 'page'));
$check(
    'Page templates registered',
    in_array('full-width', $pageTemplates, true) && in_array('landing', $pageTemplates, true)
);

$seo = Understack\Seo\Seo::current();
$check('SEO foundation registered', $seo instanceof Understack\Seo\Seo);

if ($seo instanceof Understack\Seo\Seo) {
    $check('SEO configuration valid', $seo->config()->issues() === [], implode('; ', $seo->config()->issues()));

    $verdict = $seo->environment();
    $nonProduction = in_array(wp_get_environment_type(), ['local', 'development', 'staging'], true);
    $check(
        'SEO environment policy',
        $verdict->noindex === $nonProduction
            || $seo->config()->string('indexability.non_production') === 'off'
            || $verdict->warnings !== [],
        sprintf('%s → %s', $verdict->type, $verdict->noindex ? 'noindex' : 'indexable')
    );

    $report = (new Understack\Seo\Diagnostics\Report($seo))->build();
    $check(
        'SEO diagnostics report',
        isset($report['ownership']['canonical'], $report['provider']['id'], $report['sitemap']['owner']),
        'provider ' . $report['provider']['label']
    );
}

if (class_exists('ACF')) {
    require __DIR__ . '/smoke-acf.php';
} else {
    $check('ACF loaded', false, 'ACF is not available (bundle it with "npm run acf:install")');
}

fwrite(STDOUT, $failures === 0 ? "Smoke test passed.\n" : sprintf("%d check(s) failed.\n", $failures));
$state->completed = true;
exit($failures === 0 ? 0 : 1);
