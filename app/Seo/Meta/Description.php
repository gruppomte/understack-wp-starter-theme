<?php

declare(strict_types=1);

namespace Understack\Seo\Meta;

use Understack\Seo\PageContext;
use Understack\Seo\PageKind;
use Understack\Seo\Text;
use WP_Post;

/**
 * Meta description fallback from content that already exists: the manual
 * excerpt, the page lead, the beginning of the content, the term or author
 * description. Never a site-wide sentence, never from protected, unpublished
 * or paginated content.
 */
final class Description
{
    public const MAX_LENGTH = 160;

    public static function resolve(PageContext $context): string
    {
        if ($context->paged > 1 || $context->preview) {
            return '';
        }

        return match ($context->kind) {
            PageKind::Singular => self::forPost($context),
            PageKind::Home => self::forPostsPage(),
            PageKind::Term => self::forTerm($context),
            PageKind::Author => self::summary((string) get_the_author_meta('description', $context->objectId)),
            PageKind::PostTypeArchive => self::forPostTypeArchive($context),
            default => '',
        };
    }

    private static function forPost(PageContext $context): string
    {
        if ($context->passwordProtected || ! $context->published) {
            return '';
        }

        return self::fromPost(get_post($context->objectId));
    }

    private static function forPostsPage(): string
    {
        $id = (int) get_option('page_for_posts');

        return $id > 0 ? self::fromPost(get_post($id)) : '';
    }

    private static function forTerm(PageContext $context): string
    {
        $term = get_term($context->objectId, $context->postType);

        return $term instanceof \WP_Term ? self::summary((string) $term->description) : '';
    }

    private static function forPostTypeArchive(PageContext $context): string
    {
        $type = get_post_type_object($context->postType);

        return $type !== null ? self::summary((string) $type->description) : '';
    }

    private static function fromPost(?WP_Post $post): string
    {
        if (! $post instanceof WP_Post || $post->post_status !== 'publish' || $post->post_password !== '') {
            return '';
        }

        $candidates = [
            (string) $post->post_excerpt,
            (string) get_post_meta($post->ID, 'page_lead', true),
            (string) $post->post_content,
        ];

        foreach ($candidates as $candidate) {
            $summary = self::summary($candidate);

            if ($summary !== '') {
                return $summary;
            }
        }

        return '';
    }

    private static function summary(string $raw): string
    {
        return Text::summary(strip_shortcodes($raw), self::MAX_LENGTH);
    }
}
