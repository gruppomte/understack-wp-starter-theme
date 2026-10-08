<?php

declare(strict_types=1);

namespace Understack\Seo\Meta;

use Understack\Seo\Config;
use Understack\Seo\PageContext;

/**
 * Open Graph and Twitter Card fallback from the data the page already has.
 */
final class Social
{
    /**
     * @return array<int, array{0: string, 1: string, 2: string}> [attribute name, key, value]
     */
    public static function tags(
        PageContext $context,
        Config $config,
        string $title,
        string $description,
        ?string $url
    ): array {
        $tags = [];
        $add = static function (string $attribute, string $key, ?string $value) use (&$tags): void {
            if ($value !== null && $value !== '') {
                $tags[] = [$attribute, $key, $value];
            }
        };

        $article = $context->isSingular()
            && in_array($context->postType, $config->list('content.article_post_types'), true);
        $image = self::image($context, $config);

        $add('property', 'og:type', $article ? 'article' : 'website');
        $add('property', 'og:title', $title);
        $add('property', 'og:description', $description);
        $add('property', 'og:url', $url);
        $add('property', 'og:site_name', get_bloginfo('name'));
        $add('property', 'og:locale', get_locale());

        if ($image !== null) {
            $add('property', 'og:image', $image['url']);
            $add('property', 'og:image:width', $image['width'] > 0 ? (string) $image['width'] : null);
            $add('property', 'og:image:height', $image['height'] > 0 ? (string) $image['height'] : null);
            $add('property', 'og:image:alt', $image['alt']);
        }

        if ($article) {
            $post = get_post($context->objectId);
            $published = $post !== null ? get_post_datetime($post) : false;
            $modified = $post !== null ? get_post_datetime($post, 'modified') : false;

            $add('property', 'article:published_time', $published ? $published->format(DATE_ATOM) : null);
            $add('property', 'article:modified_time', $modified ? $modified->format(DATE_ATOM) : null);
        }

        $add('name', 'twitter:card', $image !== null ? $config->string('social.twitter_card') : 'summary');
        $add('name', 'twitter:title', $title);
        $add('name', 'twitter:description', $description);
        $add('name', 'twitter:image', $image !== null ? $image['url'] : null);
        $add('name', 'twitter:site', $config->string('social.twitter_site'));

        return $tags;
    }

    /**
     * Featured image of the page, else the configured default image, else nothing.
     *
     * @return array{url: string, width: int, height: int, alt: string}|null
     */
    public static function image(PageContext $context, Config $config): ?array
    {
        $featured = $context->isSingular() ? self::featuredImage($context->objectId, $config) : null;

        return $featured ?? self::attachment($config->int('social.default_image'), $config);
    }

    /**
     * The featured image of a post in the configured social size, or null.
     *
     * @return array{url: string, width: int, height: int, alt: string}|null
     */
    public static function featuredImage(int $postId, Config $config): ?array
    {
        return has_post_thumbnail($postId) ? self::attachment((int) get_post_thumbnail_id($postId), $config) : null;
    }

    /**
     * @return array{url: string, width: int, height: int, alt: string}|null
     */
    private static function attachment(int $id, Config $config): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $size = $config->string('social.image_size');
        $source = wp_get_attachment_image_src($id, $size === '' ? 'wide' : $size);

        if (! is_array($source) || ! is_string($source[0]) || $source[0] === '') {
            return null;
        }

        return [
            'url' => $source[0],
            'width' => (int) $source[1],
            'height' => (int) $source[2],
            'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
        ];
    }
}
