<?php

declare(strict_types=1);

namespace Understack\Seo;

use WP_Post;
use WP_Query;
use WP_Term;
use WP_User;

/**
 * What the current request is, read once from the main query so every module
 * works from the same facts.
 */
final class PageContext
{
    public function __construct(
        public readonly PageKind $kind,
        public readonly string $postType = '',
        public readonly int $objectId = 0,
        public readonly int $paged = 1,
        public readonly bool $preview = false,
        public readonly bool $published = true,
        public readonly bool $passwordProtected = false,
    ) {
    }

    public static function fromQuery(WP_Query $query): self
    {
        $paged = max(1, (int) $query->get('paged', 1));

        if ($query->is_404()) {
            return new self(PageKind::NotFound);
        }

        if ($query->is_search()) {
            return new self(PageKind::Search, paged: $paged);
        }

        if ($query->is_singular()) {
            $post = $query->get_queried_object();

            if ($post instanceof WP_Post) {
                return new self(
                    PageKind::Singular,
                    (string) $post->post_type,
                    (int) $post->ID,
                    max(1, (int) $query->get('page', 1)),
                    $query->is_preview(),
                    $post->post_status === 'publish',
                    $post->post_password !== '',
                );
            }
        }

        if ($query->is_front_page()) {
            return new self(PageKind::FrontPage, paged: $paged);
        }

        if ($query->is_home()) {
            return new self(PageKind::Home, paged: $paged);
        }

        if ($query->is_post_type_archive()) {
            $type = $query->get('post_type');
            $type = is_array($type) ? reset($type) : $type;

            return new self(PageKind::PostTypeArchive, is_string($type) ? $type : '', 0, $paged);
        }

        if ($query->is_author()) {
            $user = $query->get_queried_object();

            return new self(PageKind::Author, '', $user instanceof WP_User ? (int) $user->ID : 0, $paged);
        }

        if ($query->is_date()) {
            return new self(PageKind::Date, paged: $paged);
        }

        if ($query->is_category() || $query->is_tag() || $query->is_tax()) {
            $term = $query->get_queried_object();

            if ($term instanceof WP_Term) {
                return new self(PageKind::Term, $term->taxonomy, (int) $term->term_id, $paged);
            }
        }

        return new self(PageKind::Other, paged: $paged);
    }

    public function isSingular(): bool
    {
        return $this->kind === PageKind::Singular;
    }

    public function isAttachment(): bool
    {
        return $this->kind === PageKind::Singular && $this->postType === 'attachment';
    }

    /**
     * Pages that can carry a canonical, social metadata and structured data.
     */
    public function isIndexable(): bool
    {
        if ($this->preview) {
            return false;
        }

        return match ($this->kind) {
            PageKind::Search, PageKind::NotFound, PageKind::Other => false,
            PageKind::Singular => $this->published,
            default => true,
        };
    }
}
