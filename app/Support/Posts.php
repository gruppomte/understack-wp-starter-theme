<?php

declare(strict_types=1);

namespace Understack\Support;

use Timber\Post;
use Timber\Term;
use Timber\Timber;

/**
 * Query helpers for post listings: cache priming and cached term lookups so a
 * listing never runs one query per item.
 */
final class Posts
{
    /**
     * Primes the post, meta, term and thumbnail caches of a listing in a few
     * queries. Call it in a controller before handing the posts to Twig.
     *
     * @param iterable<mixed> $posts
     */
    public static function prime(iterable $posts): void
    {
        $ids = [];

        foreach ($posts as $post) {
            if ($post instanceof Post) {
                $ids[] = (int) $post->ID;
            }
        }

        if ($ids === []) {
            return;
        }

        _prime_post_caches($ids, true, true);

        $thumbnails = [];

        foreach ($ids as $id) {
            $thumbnail = (int) get_post_thumbnail_id($id);

            if ($thumbnail > 0) {
                $thumbnails[] = $thumbnail;
            }
        }

        if ($thumbnails !== []) {
            _prime_post_caches(array_values(array_unique($thumbnails)), false, true);
        }
    }

    /**
     * Terms of a post from the object term cache (primed by WP_Query), wrapped as
     * Timber terms. Unlike Timber's Post::terms() this never issues a query per post.
     *
     * @return Term[]
     */
    public static function terms(Post|int $post, string $taxonomy): array
    {
        $id = $post instanceof Post ? (int) $post->ID : $post;
        $terms = get_the_terms($id, $taxonomy);

        if (! is_array($terms) || $terms === []) {
            return [];
        }

        $wrapped = Timber::get_terms($terms);
        $result = [];

        foreach ($wrapped as $term) {
            if ($term instanceof Term) {
                $result[] = $term;
            }
        }

        return $result;
    }
}
