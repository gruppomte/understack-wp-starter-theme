<?php

declare(strict_types=1);

namespace Understack\Support;

use Timber\Image as TimberImage;

/**
 * Renders responsive attachment markup through the WordPress media API so
 * srcset, sizes, width/height, alt text and lazy loading stay correct.
 */
final class Image
{
    /**
     * @param TimberImage|int|string|null $image Attachment ID or Timber image.
     * @param array<string, mixed> $attributes Extra attributes. "priority" => true marks the LCP image
     *                                         (eager loading + fetchpriority=high). "alt" => '' makes the
     *                                         image decorative; omit "alt" to keep the editor-provided text.
     */
    public static function render(mixed $image, string $size = 'large', array $attributes = []): string
    {
        $id = $image instanceof TimberImage ? (int) $image->ID : (int) $image;

        if ($id <= 0) {
            return '';
        }

        $priority = (bool) ($attributes['priority'] ?? false);
        unset($attributes['priority']);

        $attributes['loading'] = $priority ? 'eager' : ($attributes['loading'] ?? 'lazy');
        $attributes['decoding'] = $attributes['decoding'] ?? 'async';

        if ($priority) {
            $attributes['fetchpriority'] = 'high';
        }

        $attributes = array_filter(
            $attributes,
            static fn (mixed $value): bool => $value !== null && $value !== false
        );

        return wp_get_attachment_image($id, $size, false, $attributes);
    }
}
