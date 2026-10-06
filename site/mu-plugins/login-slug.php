<?php

/**
 * Plugin Name: Login Slug
 * Description: Moves the login screen to the slug defined by WP_LOGIN_SLUG in wp-config.php.
 */

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- a must-use plugin declares its class and boots it.

declare(strict_types=1);

namespace LoginSlug;

defined('ABSPATH') || exit;

/**
 * Site-level must-use plugin, installed by "npm run local:setup" and deployed
 * alongside the theme (wp-content/mu-plugins). Serves the login screen at
 * /{slug}/ and answers "not found" to wp-login.php and wp-admin for visitors who
 * are not logged in. Inactive without the constant; it never changes usernames
 * or credentials. Pretty permalinks are required.
 */
final class LoginSlug
{
    private const RESERVED = [
        'admin', 'login', 'dashboard', 'wp-admin', 'wp-login', 'wp-login.php', 'wp-content',
        'wp-includes', 'wp-json', 'index.php', 'feed', 'sitemap.xml', 'robots.txt',
    ];

    private ?bool $enabled = null;

    private bool $directLoginRequest = false;

    private function __construct(private readonly string $slug)
    {
    }

    public static function boot(): void
    {
        $configured = self::configuredSlug();

        if ($configured === null || is_multisite()) {
            return;
        }

        $slug = trim($configured, '/');

        $invalid = $slug === ''
            || preg_match('/^[a-z0-9_-]+$/i', $slug) !== 1
            || in_array(strtolower($slug), self::RESERVED, true);

        if ($invalid) {
            self::notice('The WP_LOGIN_SLUG constant is not a valid slug; the login address has not been changed.');

            return;
        }

        (new self($slug))->register();
    }

    /**
     * The slug from wp-config.php (WP_LOGIN_SLUG, or the earlier LOGIN_SLUG name).
     */
    private static function configuredSlug(): ?string
    {
        foreach (['WP_LOGIN_SLUG', 'LOGIN_SLUG'] as $constant) {
            if (defined($constant) && is_string(constant($constant))) {
                return constant($constant);
            }
        }

        return null;
    }

    private function register(): void
    {
        add_action('plugins_loaded', [$this, 'onPluginsLoaded'], 9999);
        add_action('wp_loaded', [$this, 'onWpLoaded']);
        add_filter('site_url', fn ($url, $path, $scheme) => $this->rewrite((string) $url, $scheme), 10, 3);
        add_filter('network_site_url', fn ($url, $path, $scheme) => $this->rewrite((string) $url, $scheme), 10, 3);
        add_filter('wp_redirect', fn ($location) => $this->rewrite((string) $location), 10, 1);

        // Core would otherwise redirect /login, /admin and /dashboard to the real screens.
        remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
    }

    /**
     * Decides what the current request is before WordPress parses it.
     */
    public function onPluginsLoaded(): void
    {
        if (! $this->isWebRequest() || ! $this->enabled() || $this->isPostPasswordSubmission()) {
            return;
        }

        $path = $this->requestPath();

        $loginFile = str_contains($path, 'wp-login.php')
            || untrailingslashit($path) === $this->homePath('wp-login');

        if (! is_admin() && $loginFile) {
            // Turn the request into one for a page that does not exist: the theme's 404 answers.
            $this->directLoginRequest = true;
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the request is rerouted on purpose.
            $_SERVER['REQUEST_URI'] = $this->trailingSlash('/' . str_repeat('-/', 10));
            $this->setPagenow('index.php');

            return;
        }

        if (untrailingslashit($path) === $this->homePath($this->slug)) {
            $this->setPagenow('wp-login.php');
        }
    }

    public function onWpLoaded(): void
    {
        if (! $this->isWebRequest() || ! $this->enabled() || $this->isPostPasswordSubmission()) {
            return;
        }

        $pagenow = (string) ($GLOBALS['pagenow'] ?? '');

        if (
            is_admin()
            && ! is_user_logged_in()
            && ! defined('WP_CLI')
            && ! wp_doing_ajax()
            && ! wp_doing_cron()
            && $pagenow !== 'admin-post.php'
        ) {
            wp_safe_redirect(home_url('/404/'));
            exit;
        }

        if ($pagenow === 'wp-login.php' && $this->useTrailingSlashes() && ! str_ends_with($this->requestPath(), '/')) {
            $query = isset($_SERVER['QUERY_STRING'])
                ? sanitize_text_field(wp_unslash((string) $_SERVER['QUERY_STRING']))
                : '';
            wp_safe_redirect($this->loginUrl() . ($query !== '' ? '?' . $query : ''));
            exit;
        }

        if ($this->directLoginRequest) {
            $this->renderNotFound();
        }

        if ($pagenow === 'wp-login.php') {
            $this->serveLoginScreen();
        }
    }

    private function serveLoginScreen(): never
    {
        // phpcs:disable WordPress.Security.NonceVerification -- wp-login.php's own parameters, read only.
        $hasAction = isset($_GET['action']) || isset($_POST['action']) || isset($_GET['interim-login']);
        $requested = isset($_REQUEST['redirect_to'])
            ? esc_url_raw(wp_unslash((string) $_REQUEST['redirect_to']))
            : '';
        // phpcs:enable WordPress.Security.NonceVerification

        if (is_user_logged_in() && ! $hasAction) {
            wp_safe_redirect($requested !== '' ? wp_validate_redirect($requested, admin_url()) : admin_url());
            exit;
        }

        // wp-login.php expects these variables to be globals.
        global $error, $interim_login, $action, $user_login;

        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    private function renderNotFound(): never
    {
        $this->setPagenow('index.php');
        defined('WP_USE_THEMES') || define('WP_USE_THEMES', true);

        wp();
        require_once ABSPATH . 'wp-includes/template-loader.php';
        exit;
    }

    /**
     * Rewrites any wp-login.php URL WordPress generates (login, logout, password reset
     * links in emails, redirects) to the custom login address.
     */
    private function rewrite(string $url, ?string $scheme = null): string
    {
        $untouched = ! $this->enabled()
            || ! str_contains($url, 'wp-login.php')
            || str_contains($url, 'wp-login.php?action=postpass');

        if ($untouched) {
            return $url;
        }

        if (is_ssl()) {
            $scheme = 'https';
        }

        $parts = explode('?', $url, 2);
        $login = $this->loginUrl($scheme);

        if (! isset($parts[1])) {
            return $login;
        }

        parse_str($parts[1], $args);

        if (isset($args['login'])) {
            $args['login'] = rawurlencode((string) $args['login']);
        }

        return add_query_arg($args, $login);
    }

    private function enabled(): bool
    {
        if ($this->enabled === null) {
            $this->enabled = (string) get_option('permalink_structure') !== '';

            if (! $this->enabled) {
                self::notice('Pretty permalinks are required for the custom login address; wp-login.php stays in use.');
            }
        }

        return $this->enabled;
    }

    private function loginUrl(?string $scheme = null): string
    {
        return $this->trailingSlash(home_url('/' . $this->slug, $scheme));
    }

    private function homePath(string $slug): string
    {
        return untrailingslashit((string) home_url('/' . $slug, 'relative'));
    }

    private function requestPath(): string
    {
        $uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';

        return (string) wp_parse_url(rawurldecode($uri), PHP_URL_PATH);
    }

    /**
     * Scripts that load WordPress without an HTTP request (WP-CLI, cron runners,
     * install tooling) must never be redirected.
     */
    private function isWebRequest(): bool
    {
        return ! empty($_SERVER['REQUEST_METHOD']) && ! empty($_SERVER['REQUEST_URI']);
    }

    private function isPostPasswordSubmission(): bool
    {
        // phpcs:disable WordPress.Security.NonceVerification -- core's password form carries no nonce.
        $submitted = isset($_GET['action'], $_POST['post_password'])
            && sanitize_key(wp_unslash((string) $_GET['action'])) === 'postpass';
        // phpcs:enable WordPress.Security.NonceVerification

        return $submitted;
    }

    private function setPagenow(string $page): void
    {
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- spoofing the screen on purpose.
        $GLOBALS['pagenow'] = $page;
    }

    private function useTrailingSlashes(): bool
    {
        return str_ends_with((string) get_option('permalink_structure'), '/');
    }

    private function trailingSlash(string $url): string
    {
        return $this->useTrailingSlashes() ? trailingslashit($url) : untrailingslashit($url);
    }

    private static function notice(string $message): void
    {
        add_action('admin_notices', static function () use ($message): void {
            if (current_user_can('manage_options')) {
                wp_admin_notice($message, ['type' => 'warning']);
            }
        });
    }
}

LoginSlug::boot();
