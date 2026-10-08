<?php

declare(strict_types=1);

namespace Understack\Seo;

use WP_Post;
use WP_Term;
use WP_User;

/**
 * Breadcrumb trail of the current request, from the site hierarchy and the
 * permalink functions: home, ancestors or parent terms, the current item and
 * the page number. Nothing is rendered unless breadcrumbs are enabled.
 */
final class Breadcrumbs
{
    /** @var array<int, array{name: string, url: string|null}>|null */
    private ?array $trail = null;

    public function __construct(private readonly Seo $seo)
    {
    }

    /**
     * Items for Twig and for the BreadcrumbList node; empty when disabled or not applicable.
     *
     * @return array<int, array{name: string, url: string|null}>
     */
    public function items(): array
    {
        if (
            ! $this->seo->config()->bool('breadcrumbs.enabled')
            || ! $this->seo->isFrontEnd()
            || ! $this->seo->allowances()['breadcrumbs']
        ) {
            return [];
        }

        return $this->trail ??= $this->build($this->seo->context());
    }

    /**
     * Keeps only items with a name, links every item but the last and, on
     * paginated listings, links the current item and appends the page number.
     * Pure, unit-tested.
     *
     * @param array<int, array{name: string, url: string|null}> $items
     * @param string $pagedUrl URL of the current page of the listing.
     * @param string $pageLabel Label with the page number already applied.
     * @return array<int, array{name: string, url: string|null}>
     */
    public static function finish(array $items, int $paged = 1, string $pagedUrl = '', string $pageLabel = ''): array
    {
        $trail = [];

        foreach ($items as $item) {
            $name = Text::plain((string) ($item['name'] ?? ''));
            $url = isset($item['url']) && is_string($item['url']) && $item['url'] !== '' ? $item['url'] : null;

            if ($name !== '') {
                $trail[] = ['name' => $name, 'url' => $url];
            }
        }

        if (count($trail) < 2) {
            return [];
        }

        if ($paged > 1 && $pageLabel !== '') {
            $trail[] = ['name' => $pageLabel, 'url' => $pagedUrl !== '' ? $pagedUrl : null];
        }

        $trail[count($trail) - 1]['url'] = null;

        return $trail;
    }

    /**
     * @return array<int, array{name: string, url: string|null}>
     */
    private function build(PageContext $context): array
    {
        $items = match ($context->kind) {
            PageKind::Home => $this->postsPage(),
            PageKind::Singular => $this->singular($context),
            PageKind::Term => $this->term($context),
            PageKind::Author => $this->author($context),
            PageKind::Date => $this->date(),
            PageKind::PostTypeArchive => $this->postTypeArchive($context->postType),
            default => [],
        };

        if ($items === []) {
            return [];
        }

        $home = $this->seo->config()->string('breadcrumbs.home_label');
        $home = $home !== '' ? $home : __('Home', 'understack-wp-starter');
        $items = array_merge([['name' => $home, 'url' => home_url('/')]], $items);
        $pagedUrl = $context->paged > 1 ? (string) Meta\Canonical::resolve($context) : '';
        /* translators: %d: page number of a paginated listing */
        $pageLabel = $context->paged > 1 ? sprintf(__('Page %d', 'understack-wp-starter'), $context->paged) : '';

        return self::finish($items, $context->paged, $pagedUrl, $pageLabel);
    }

    /**
     * @return array<int, array{name: string, url: string|null}>
     */
    private function postsPage(): array
    {
        $id = (int) get_option('page_for_posts');

        return $id > 0 ? [$this->post($id)] : [];
    }

    /**
     * @return array<int, array{name: string, url: string|null}>
     */
    private function singular(PageContext $context): array
    {
        $post = get_post($context->objectId);

        if (! $post instanceof WP_Post) {
            return [];
        }

        $items = [];

        if ($post->post_type === 'post') {
            $items = $this->postsPage();
            $categories = get_the_category($post->ID);
            $category = is_array($categories) && isset($categories[0]) && $categories[0] instanceof WP_Term
                ? $categories[0]
                : null;

            if ($category !== null) {
                $items = array_merge($items, $this->termChain($category));
            }
        } elseif ($post->post_type !== 'page' && $post->post_type !== 'attachment') {
            $items = $this->postTypeArchive($post->post_type);
        }

        if (is_post_type_hierarchical($post->post_type)) {
            foreach (array_reverse(get_post_ancestors($post)) as $ancestorId) {
                $ancestor = get_post($ancestorId);

                if ($ancestor instanceof WP_Post && $ancestor->post_status === 'publish') {
                    $items[] = $this->post($ancestor->ID);
                }
            }
        } elseif ($post->post_type === 'attachment' && $post->post_parent > 0) {
            $parent = get_post($post->post_parent);

            if ($parent instanceof WP_Post && $parent->post_status === 'publish') {
                $items[] = $this->post($parent->ID);
            }
        }

        $items[] = $this->post($post->ID);

        return $items;
    }

    /**
     * @return array<int, array{name: string, url: string|null}>
     */
    private function term(PageContext $context): array
    {
        $term = get_term($context->objectId, $context->postType);

        if (! $term instanceof WP_Term) {
            return [];
        }

        $items = in_array($term->taxonomy, ['category', 'post_tag'], true) ? $this->postsPage() : [];

        return array_merge($items, $this->termChain($term));
    }

    /**
     * Parent terms (top first) followed by the term itself; terms without a valid link are skipped.
     *
     * @return array<int, array{name: string, url: string|null}>
     */
    private function termChain(WP_Term $term): array
    {
        $items = [];
        $ids = array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy'));
        $ids[] = $term->term_id;

        foreach ($ids as $id) {
            $current = get_term($id, $term->taxonomy);
            $link = $current instanceof WP_Term ? get_term_link($current) : null;

            if ($current instanceof WP_Term && is_string($link)) {
                $items[] = ['name' => $current->name, 'url' => $link];
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{name: string, url: string|null}>
     */
    private function author(PageContext $context): array
    {
        $user = get_userdata($context->objectId);

        if (! $user instanceof WP_User) {
            return [];
        }

        $name = StructuredData\Author::isPublicName($user->display_name, (array) $user->data)
            ? $user->display_name
            : __('Author', 'understack-wp-starter');

        return [['name' => $name, 'url' => get_author_posts_url($user->ID)]];
    }

    /**
     * @return array<int, array{name: string, url: string|null}>
     */
    private function date(): array
    {
        $year = (int) get_query_var('year');
        $month = (int) get_query_var('monthnum');
        $day = (int) get_query_var('day');

        if ($year <= 0) {
            return [];
        }

        $items = [['name' => (string) $year, 'url' => get_year_link($year)]];

        if ($month > 0) {
            $items[] = [
                'name' => wp_date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?: sprintf('%d-%02d', $year, $month),
                'url' => get_month_link($year, $month),
            ];
        }

        if ($month > 0 && $day > 0) {
            $items[] = [
                'name' => wp_date('j F Y', mktime(0, 0, 0, $month, $day, $year))
                    ?: sprintf('%d-%02d-%02d', $year, $month, $day),
                'url' => get_day_link($year, $month, $day),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{name: string, url: string|null}>
     */
    private function postTypeArchive(string $postType): array
    {
        $type = get_post_type_object($postType);
        $link = $type !== null && $type->has_archive ? get_post_type_archive_link($postType) : false;

        if ($type === null || ! is_string($link) || $link === '') {
            return [];
        }

        return [['name' => (string) $type->labels->name, 'url' => $link]];
    }

    /**
     * @return array{name: string, url: string|null}
     */
    private function post(int $id): array
    {
        $link = get_permalink($id);

        return ['name' => get_the_title($id), 'url' => is_string($link) ? $link : null];
    }
}
