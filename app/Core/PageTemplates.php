<?php

declare(strict_types=1);

namespace Understack\Core;

use Understack\Support\Registrable;
use WP_Post;
use WP_Theme;

/**
 * Page templates selectable from the page editor. Each slug maps to
 * views/pages/templates/{slug}.twig.
 */
final class PageTemplates implements Registrable
{
    /**
     * @return array<string, string> Template slug => label.
     */
    public static function all(): array
    {
        return [
            'full-width' => __('Full width', 'understack-wp-starter'),
            'landing' => __('Landing page', 'understack-wp-starter'),
        ];
    }

    public function register(): void
    {
        add_filter('theme_page_templates', [$this, 'add'], 10, 4);
    }

    /**
     * @param array<string, string> $templates
     * @return array<string, string>
     */
    public function add(array $templates, WP_Theme $theme, ?WP_Post $post, string $postType): array
    {
        if ($postType !== 'page') {
            return $templates;
        }

        return array_merge($templates, self::all());
    }

    /**
     * Twig view for the template assigned to the current page, if any.
     */
    public static function currentView(): ?string
    {
        $slug = (string) get_page_template_slug();

        return $slug !== '' && isset(self::all()[$slug]) ? 'pages/templates/' . $slug . '.twig' : null;
    }
}
