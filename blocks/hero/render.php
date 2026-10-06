<?php

/**
 * Included by ACF with $block, $content, $is_preview and $post_id in scope.
 *
 * @var array<string, mixed> $block
 * @var string $content
 * @var bool $is_preview
 * @var int $post_id
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

Understack\Blocks\Renderer::render(
    $block,
    (string) ($content ?? ''),
    (bool) ($is_preview ?? false),
    (int) ($post_id ?? 0)
);
