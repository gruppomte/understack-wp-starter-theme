<?php

declare(strict_types=1);

namespace Understack\Core;

use Understack\Support\Registrable;

/**
 * Block editor policy. The editor stays enabled; theme.json limits colours,
 * sizes and spacing to the design tokens, and this class can restrict the
 * available blocks per project.
 */
final class Editor implements Registrable
{
    /**
     * Block names allowed in the inserter, or null to allow every registered block.
     * Example: ['core/paragraph', 'core/heading', 'core/list', 'core/image', 'acf/hero'].
     *
     * @var string[]|null
     */
    private const ALLOWED_BLOCKS = null;

    public function register(): void
    {
        add_filter('allowed_block_types_all', [$this, 'allowedBlocks'], 10, 2);
    }

    /**
     * @param bool|string[] $allowed
     * @return bool|string[]
     */
    public function allowedBlocks(bool|array $allowed, \WP_Block_Editor_Context $context): bool|array
    {
        /** @var string[]|null $list */
        $list = apply_filters(Theme::SLUG . '/editor/allowed_blocks', self::ALLOWED_BLOCKS, $context);

        return is_array($list) ? array_values($list) : $allowed;
    }
}
