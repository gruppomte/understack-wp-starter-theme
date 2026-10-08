<?php

declare(strict_types=1);

namespace Understack\Seo;

use Twig\Environment as TwigEnvironment;
use Twig\TwigFunction;
use Understack\Seo\Crawlers\Policy as CrawlerPolicy;
use Understack\Seo\Diagnostics\Command;
use Understack\Seo\Diagnostics\SiteHealth;
use Understack\Seo\Identity\Identity;
use Understack\Seo\Identity\Repository;
use Understack\Seo\Indexability\Environment;
use Understack\Seo\Indexability\Policy;
use Understack\Seo\Indexability\Verdict;
use Understack\Seo\Meta\Canonical;
use Understack\Seo\Meta\Head;
use Understack\Support\Registrable;
use WP_Query;

/**
 * SEO/GEO foundation entry point: loads the configuration, resolves the
 * request state once and wires the modules. See docs/seo-geo/.
 */
final class Seo implements Registrable
{
    private static ?self $current = null;

    private ?Config $config = null;

    private ?Provider $provider = null;

    private ?PageContext $context = null;

    private ?Ownership $ownership = null;

    private ?Verdict $environment = null;

    private ?Identity $identity = null;

    private ?Breadcrumbs $breadcrumbs = null;

    public static function current(): ?self
    {
        return self::$current;
    }

    public function register(): void
    {
        self::$current = $this;

        add_action('after_setup_theme', [$this, 'boot'], 20);
    }

    public function boot(): void
    {
        if (! $this->config()->bool('enabled')) {
            return;
        }

        (new Policy($this))->register();
        (new Head($this))->register();
        add_filter('get_canonical_url', [Canonical::class, 'withoutImplicitCommentPage'], 10, 2);
        add_filter('timber/twig', [$this, 'extendTwig']);
        (new Sitemap($this))->register();
        (new CrawlerPolicy($this))->register();
        (new SiteHealth($this))->register();

        if (defined('WP_CLI') && constant('WP_CLI') && class_exists('WP_CLI')) {
            (new Command($this))->register();
        }
    }

    public function config(): Config
    {
        return $this->config ??= Config::load();
    }

    public function provider(): Provider
    {
        if ($this->provider === null) {
            $forced = $this->config()->string('provider');
            $this->provider = $forced === 'auto' || Provider::tryFrom($forced) === null
                ? Provider::detect('defined')
                : Provider::from($forced);
        }

        return $this->provider;
    }

    public function ownership(): Ownership
    {
        return $this->ownership ??= Ownership::resolve($this->provider(), $this->config());
    }

    public function environment(): Verdict
    {
        return $this->environment ??= Environment::current($this->config());
    }

    public function identity(): Identity
    {
        return $this->identity ??= Repository::load();
    }

    public function breadcrumbs(): Breadcrumbs
    {
        return $this->breadcrumbs ??= new Breadcrumbs($this);
    }

    /**
     * breadcrumbs() in Twig: the trail of the current request, empty unless enabled.
     */
    public function extendTwig(TwigEnvironment $twig): TwigEnvironment
    {
        $twig->addFunction(new TwigFunction('breadcrumbs', [$this->breadcrumbs(), 'items']));

        return $twig;
    }

    /**
     * The main query, read once after WordPress has parsed the request.
     */
    public function context(): PageContext
    {
        if ($this->context === null) {
            $query = $GLOBALS['wp_query'] ?? null;
            $this->context = $query instanceof WP_Query && did_action('wp') > 0
                ? PageContext::fromQuery($query)
                : new PageContext(PageKind::Other);
        }

        return $this->context;
    }

    /**
     * A regular front-end page request: not admin, AJAX, cron, REST, feed,
     * embed, robots.txt or the Customizer preview.
     */
    public function isFrontEnd(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || wp_is_serving_rest_request()) {
            return false;
        }

        if (did_action('wp') === 0) {
            return false;
        }

        return ! is_feed() && ! is_embed() && ! is_robots() && ! is_trackback() && ! is_customize_preview();
    }
}
