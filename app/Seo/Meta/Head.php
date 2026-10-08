<?php

declare(strict_types=1);

namespace Understack\Seo\Meta;

use Understack\Seo\Seo;
use Understack\Seo\Signal;
use Understack\Seo\StructuredData\Collector;
use Understack\Seo\StructuredData\Graph;
use Understack\Seo\StructuredData\Serializer;
use Understack\Seo\Text;

/**
 * The theme's single wp_head callback: description, canonical and social
 * metadata, each only when the theme owns the signal on this request.
 */
final class Head
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'render'], 2);
    }

    /**
     * @return array{
     *     description: string,
     *     canonical: string|null,
     *     social: array<int, array{0: string, 1: string, 2: string}>,
     *     jsonld: string
     * }
     */
    public function resolve(): array
    {
        $context = $this->seo->context();
        $config = $this->seo->config();
        $ownership = $this->seo->ownership();

        $description = $ownership->themeOwns(Signal::Description) ? Description::resolve($context) : '';
        $canonical = $ownership->themeOwns(Signal::Canonical) ? Canonical::resolve($context) : null;
        $social = [];

        if ($ownership->themeOwns(Signal::Social) && $context->isIndexable()) {
            $url = $canonical;

            if ($url === null && $context->isSingular()) {
                $coreCanonical = wp_get_canonical_url($context->objectId);
                $url = is_string($coreCanonical) && $coreCanonical !== '' ? $coreCanonical : null;
            }

            $social = Social::tags($context, $config, Text::plain(wp_get_document_title()), $description, $url);
        }

        $jsonld = '';

        if ($ownership->themeOwns(Signal::StructuredData) && $context->isIndexable() && ! self::isWooCommercePage()) {
            $jsonld = Serializer::encode(Graph::build((new Collector($this->seo))->collect()));
        }

        return ['description' => $description, 'canonical' => $canonical, 'social' => $social, 'jsonld' => $jsonld];
    }

    /**
     * WooCommerce emits its own WebSite, BreadcrumbList and Product nodes on shop pages.
     */
    private static function isWooCommercePage(): bool
    {
        return function_exists('is_woocommerce') && is_woocommerce();
    }

    public function render(): void
    {
        if (! $this->seo->isFrontEnd()) {
            return;
        }

        $resolved = $this->resolve();

        if ($resolved['description'] !== '') {
            printf('<meta name="description" content="%s">' . "\n", esc_attr($resolved['description']));
        }

        if ($resolved['canonical'] !== null) {
            printf('<link rel="canonical" href="%s">' . "\n", esc_url($resolved['canonical']));
        }

        foreach ($resolved['social'] as [$attribute, $key, $value]) {
            printf(
                '<meta %s="%s" content="%s">' . "\n",
                $attribute === 'name' ? 'name' : 'property',
                esc_attr($key),
                esc_attr($value)
            );
        }

        if ($resolved['jsonld'] !== '') {
            // The JSON is encoded with the HTML-safe flags of Serializer (no "<", ">", "&", quotes).
            wp_print_inline_script_tag($resolved['jsonld'], ['type' => 'application/ld+json']);
        }
    }
}
