<?php

declare(strict_types=1);

namespace Understack\Core;

use Understack\Support\Environment;
use Understack\Support\Registrable;
use Understack\Support\ViteManifest;

/**
 * Enqueues the Vite build through the WordPress Script Modules API. In local
 * environments a running dev server (detected through public/hot) is used instead
 * of the compiled files.
 */
final class Assets implements Registrable
{
    private const ENTRY_SCRIPT = 'src/js/app.js';

    private const ENTRY_STYLE = 'src/css/app.css';

    private const ENTRY_EDITOR = 'src/css/editor.css';

    private const BUILD_DIRECTORY = 'public/build';

    private static ?ViteManifest $manifest = null;

    /** @var string|null|false */
    private static string|null|false $devServer = false;

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_head', [$this, 'printDocumentClasses'], 0);
        add_action('wp_head', [$this, 'preloadEntryModule'], 2);
        add_action('admin_notices', [$this, 'missingBuildNotice']);
    }

    /**
     * Script modules are printed in the footer on classic themes; preloading the
     * entry module from the head lets the browser fetch it early.
     */
    public function preloadEntryModule(): void
    {
        if (self::devServer() !== null) {
            return;
        }

        $script = self::manifest()->entry(self::ENTRY_SCRIPT);

        if ($script === null) {
            return;
        }

        printf('<link rel="modulepreload" href="%s">' . "\n", esc_url($this->buildUrl((string) $script['file'])));
    }

    public function enqueue(): void
    {
        $handle = Theme::SLUG . '-app';
        $devServer = self::devServer();

        if ($devServer !== null) {
            wp_enqueue_script_module($handle . '-vite-client', $devServer . '/@vite/client', [], null);
            wp_enqueue_style($handle, $devServer . '/' . self::ENTRY_STYLE, [], null);
            wp_enqueue_script_module($handle, $devServer . '/' . self::ENTRY_SCRIPT, [], null);
        } else {
            $this->enqueueBuild($handle);
        }

        if (is_singular() && comments_open() && get_option('thread_comments')) {
            wp_enqueue_script('comment-reply');
        }
    }

    /**
     * Marks the document as JavaScript-capable before first paint so the
     * navigation can switch from its no-JS fallback to the toggled version.
     */
    public function printDocumentClasses(): void
    {
        wp_print_inline_script_tag('document.documentElement.classList.add("js");');
    }

    public function missingBuildNotice(): void
    {
        if (! current_user_can('manage_options') || self::devServer() !== null || self::manifest()->exists()) {
            return;
        }

        wp_admin_notice(
            esc_html__(
                'Compiled theme assets are missing. Run "npm run build" (or "npm run dev") inside the theme directory.',
                'understack-wp-starter'
            ),
            ['type' => 'warning']
        );
    }

    /**
     * Stylesheet for add_editor_style(): a theme-relative build path, or the dev
     * server URL (served as plain CSS thanks to "?direct") while `vite` runs.
     */
    public static function editorStylesheet(): ?string
    {
        $devServer = self::devServer();

        if ($devServer !== null) {
            return $devServer . '/' . self::ENTRY_EDITOR . '?direct';
        }

        $entry = self::manifest()->entry(self::ENTRY_EDITOR);

        return $entry === null ? null : self::BUILD_DIRECTORY . '/' . ltrim((string) $entry['file'], '/');
    }

    /**
     * URL of a Vite entry: the dev server while it runs, otherwise the hashed
     * build file. Null when the entry is not part of the build.
     */
    public static function entryUrl(string $entry): ?string
    {
        $devServer = self::devServer();

        if ($devServer !== null) {
            return $devServer . '/' . ltrim($entry, '/');
        }

        $item = self::manifest()->entry($entry);

        return $item === null ? null : Theme::url(self::BUILD_DIRECTORY . '/' . ltrim((string) $item['file'], '/'));
    }

    /**
     * Registers a stylesheet entry under a handle (not enqueued). True when registered.
     */
    public static function registerStyle(string $handle, string $entry): bool
    {
        $url = self::entryUrl($entry);

        // wp_register_style() returns false for a handle registered earlier in the request.
        return $url !== null && (wp_style_is($handle, 'registered') || wp_register_style($handle, $url, [], null));
    }

    /**
     * Registers a JavaScript entry as a script module (not enqueued). True when registered.
     */
    public static function registerModule(string $id, string $entry): bool
    {
        $url = self::entryUrl($entry);

        if ($url === null) {
            return false;
        }

        wp_register_script_module($id, $url, [], null);

        return true;
    }

    /**
     * Enqueues a third-party script (analytics, maps, chat) without blocking
     * rendering. Extra attributes let a consent manager gate it, for example
     * ['type' => 'text/plain', 'data-cookieconsent' => 'statistics'].
     *
     * @param array<string, string> $attributes
     */
    public static function enqueueExternal(
        string $handle,
        string $src,
        array $attributes = [],
        string $strategy = 'async'
    ): void {
        wp_enqueue_script($handle, $src, [], null, ['in_footer' => true, 'strategy' => $strategy]);

        if ($attributes === []) {
            return;
        }

        add_filter('wp_script_attributes', static function (array $current) use ($handle, $attributes): array {
            if (($current['id'] ?? '') === $handle . '-js') {
                return array_merge($current, $attributes);
            }

            return $current;
        });
    }

    public static function manifest(): ViteManifest
    {
        return self::$manifest ??= new ViteManifest(
            Theme::path(self::BUILD_DIRECTORY . '/.vite/manifest.json')
        );
    }

    public static function devServer(): ?string
    {
        if (self::$devServer !== false) {
            return self::$devServer;
        }

        self::$devServer = null;

        if (! Environment::isLocal()) {
            return null;
        }

        $hotFile = Theme::path('public/hot');

        if (! is_readable($hotFile)) {
            return null;
        }

        $origin = untrailingslashit(trim((string) file_get_contents($hotFile)));

        if (preg_match('#^https?://[^\s]+$#', $origin) === 1 && self::isReachable($origin)) {
            self::$devServer = $origin;
        }

        return self::$devServer;
    }

    /**
     * A hot file left behind by a dev server that is no longer running must not
     * break the site: the compiled assets are used when nothing answers.
     */
    private static function isReachable(string $origin): bool
    {
        $response = wp_remote_get($origin . '/@vite/client', [
            'timeout' => 1,
            'limit_response_size' => 1024,
        ]);

        return ! is_wp_error($response);
    }

    private function enqueueBuild(string $handle): void
    {
        $manifest = self::manifest();

        $style = $manifest->entry(self::ENTRY_STYLE);

        if ($style !== null) {
            wp_enqueue_style($handle, $this->buildUrl((string) $style['file']), [], null);
        }

        $script = $manifest->entry(self::ENTRY_SCRIPT);

        if ($script === null) {
            return;
        }

        foreach ($manifest->css(self::ENTRY_SCRIPT) as $index => $css) {
            wp_enqueue_style($handle . '-' . $index, $this->buildUrl($css), [], null);
        }

        $dependencies = [];

        foreach ((array) ($script['imports'] ?? []) as $import) {
            $chunk = $manifest->entry((string) $import);

            if ($chunk === null) {
                continue;
            }

            $id = $handle . '-' . sanitize_key((string) $import);
            wp_register_script_module($id, $this->buildUrl((string) $chunk['file']), [], null);
            $dependencies[] = $id;
        }

        wp_enqueue_script_module($handle, $this->buildUrl((string) $script['file']), $dependencies, null);
    }

    private function buildUrl(string $file): string
    {
        return Theme::url(self::BUILD_DIRECTORY . '/' . ltrim($file, '/'));
    }
}
