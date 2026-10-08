<?php

declare(strict_types=1);

namespace Understack\Seo\Meta;

use Understack\Seo\PageContext;
use Understack\Seo\PageKind;
use WP_Post;
use WP_Query;

/**
 * Canonical URLs for the pages core leaves without one (front page, posts
 * index, archives, paginated listings). Singular pages keep core's
 * rel_canonical. URLs are built from permalink functions, never from the request.
 */
final class Canonical
{
    public static function resolve(PageContext $context): ?string
    {
        if (! $context->isIndexable() || $context->isSingular()) {
            return null;
        }

        $base = match ($context->kind) {
            PageKind::FrontPage => home_url('/'),
            PageKind::Home => self::postsPageUrl(),
            PageKind::Term => self::termUrl($context),
            PageKind::Author => get_author_posts_url($context->objectId),
            PageKind::Date => self::dateUrl(),
            PageKind::PostTypeArchive => get_post_type_archive_link($context->postType),
            default => '',
        };

        if (! is_string($base) || $base === '') {
            return null;
        }

        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        $pretty = $rewrite instanceof \WP_Rewrite && $rewrite->using_permalinks();

        return self::paged(
            $base,
            $context->paged,
            $pretty,
            $pretty ? (string) $rewrite->pagination_base : 'page',
            ! $pretty || (bool) $rewrite->use_trailing_slashes
        );
    }

    /**
     * Appends the pagination segment the way WordPress builds listing URLs.
     * Pure: unit-tested without WordPress.
     */
    public static function paged(
        string $base,
        int $paged,
        bool $prettyPermalinks,
        string $paginationBase = 'page',
        bool $trailingSlash = true
    ): string {
        if ($paged <= 1) {
            return $base;
        }

        if (! $prettyPermalinks || str_contains($base, '?')) {
            return $base . (str_contains($base, '?') ? '&' : '?') . 'paged=' . $paged;
        }

        $url = rtrim($base, '/') . '/' . trim($paginationBase, '/') . '/' . $paged;

        return $trailingSlash ? $url . '/' : $url;
    }

    /**
     * Core appends the comment page to a post's canonical whenever "cpage" is
     * set. The theme sets it before the head to paginate comments, so every
     * post with paged comments would be canonicalised to its newest comment
     * page. Keep the comment page only when the request asked for one.
     *
     * @param string $url Canonical URL computed by core.
     */
    public static function withoutImplicitCommentPage(string $url, WP_Post $post): string
    {
        $query = $GLOBALS['wp_query'] ?? null;

        if (
            ! $query instanceof WP_Query
            || (int) $query->get_queried_object_id() !== (int) $post->ID
            || ! empty($query->query['cpage'])
            || (int) $query->get('cpage', 0) === 0
        ) {
            return $url;
        }

        $permalink = get_permalink($post);

        if (! is_string($permalink) || $permalink === '') {
            return $url;
        }

        $page = (int) $query->get('page', 0);

        if ($page >= 2) {
            $rewrite = $GLOBALS['wp_rewrite'] ?? null;
            $permalink = $rewrite instanceof \WP_Rewrite && $rewrite->using_permalinks()
                ? trailingslashit($permalink) . user_trailingslashit((string) $page, 'single_paged')
                : add_query_arg('page', $page, $permalink);
        }

        return $permalink;
    }

    private static function postsPageUrl(): string
    {
        $id = (int) get_option('page_for_posts');
        $link = $id > 0 ? get_permalink($id) : false;

        return is_string($link) ? $link : home_url('/');
    }

    private static function termUrl(PageContext $context): string
    {
        $link = get_term_link($context->objectId, $context->postType);

        return is_string($link) ? $link : '';
    }

    private static function dateUrl(): string
    {
        $year = (int) get_query_var('year');
        $month = (int) get_query_var('monthnum');
        $day = (int) get_query_var('day');

        if ($year <= 0) {
            return '';
        }

        if ($day > 0) {
            return get_day_link($year, $month, $day);
        }

        return $month > 0 ? get_month_link($year, $month) : get_year_link($year);
    }
}
