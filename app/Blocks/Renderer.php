<?php

declare(strict_types=1);

namespace Understack\Blocks;

use Timber\Timber;
use WP_Block_Supports;

/**
 * Renders a block through Timber. Called from blocks/<name>/render.php, which
 * ACF includes with $block, $content, $is_preview and $post_id in scope.
 */
final class Renderer
{
    /**
     * @param array<string, mixed> $block
     */
    public static function render(array $block, string $content = '', bool $isPreview = false, int $postId = 0): void
    {
        $name = (string) ($block['name'] ?? '');
        $slug = substr($name, (int) strrpos($name, '/') + 1);

        $context = Timber::context_global();
        $context['block'] = $block;
        $context['fields'] = function_exists('get_fields') ? (get_fields() ?: []) : [];
        $context['content'] = $content;
        $context['is_preview'] = $isPreview;
        $context['post_id'] = $postId;
        $context['wrapper_attributes'] = self::wrapperAttributes($block, $slug);

        Timber::render('@blocks/' . $slug . '/block.twig', $context);
    }

    /**
     * Root element attributes: block supports (alignment, anchor) plus a stable
     * class per block.
     *
     * @param array<string, mixed> $block
     */
    private static function wrapperAttributes(array $block, string $slug): string
    {
        $classes = ['block-' . $slug];

        if (! empty($block['className'])) {
            $classes[] = (string) $block['className'];
        }

        $attributes = ['class' => implode(' ', $classes)];

        if (! empty($block['anchor'])) {
            $attributes['id'] = (string) $block['anchor'];
        }

        if (WP_Block_Supports::$block_to_render !== null) {
            return get_block_wrapper_attributes($attributes);
        }

        $pairs = [];

        if (! empty($block['align'])) {
            $attributes['class'] .= ' align' . $block['align'];
        }

        foreach ($attributes as $attribute => $value) {
            $pairs[] = sprintf('%s="%s"', $attribute, esc_attr($value));
        }

        return implode(' ', $pairs);
    }
}
