<?php

declare(strict_types=1);

namespace Understack\Blocks;

use Understack\Core\Assets;
use Understack\Core\Theme;
use Understack\Support\Registrable;

/**
 * Registers every block defined in blocks/<name>/block.json.
 *
 * Convention per block directory:
 *   block.json   metadata (apiVersion 3); ACF blocks carry an "acf" key
 *   render.php   hands rendering to Blocks\Renderer
 *   block.twig   the template, rendered through Timber as @blocks/<name>/block.twig
 *   style.css    optional, compiled by Vite and loaded only where the block renders
 *   editor.css   optional, editor-only styles
 *   view.js      optional, front-end script module loaded only where the block renders
 */
final class Blocks implements Registrable
{
    private const DIRECTORY = 'blocks';

    public function register(): void
    {
        add_action('init', [$this, 'registerBlocks']);
        add_filter('block_type_metadata', [$this, 'attachAssets']);
        add_filter('timber/locations', [$this, 'twigNamespace']);
        // Block styles are enqueued when a block renders instead of on every page.
        add_filter('should_load_separate_core_block_assets', '__return_true');
    }

    public function registerBlocks(): void
    {
        foreach (glob(Theme::path(self::DIRECTORY) . '/*/block.json') ?: [] as $file) {
            $metadata = json_decode((string) file_get_contents($file), true);

            if (! is_array($metadata)) {
                continue;
            }

            // ACF blocks need ACF to render and to provide their fields.
            if (isset($metadata['acf']) && ! function_exists('acf_register_block_type')) {
                continue;
            }

            register_block_type(dirname($file));
        }
    }

    /**
     * Attaches the Vite-built assets of a theme block to its metadata. Handles are
     * resolved from the manifest (or the dev server) so block.json never carries
     * hashed file names.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function attachAssets(array $metadata): array
    {
        // Core stores the block.json path through realpath(): resolve ours the same
        // way so symlinked theme directories (release-based deployments) still match.
        $file = wp_normalize_path((string) ($metadata['file'] ?? ''));
        $directory = wp_normalize_path((string) realpath(Theme::path(self::DIRECTORY))) . '/';

        if ($file === '' || $directory === '/' || ! str_starts_with($file, $directory)) {
            return $metadata;
        }

        $slug = basename(dirname($file));
        $source = self::DIRECTORY . '/' . $slug . '/';
        $handle = Theme::SLUG . '-block-' . $slug;

        if (Assets::registerStyle($handle, $source . 'style.css')) {
            $metadata['style'] = $handle;
        }

        if (Assets::registerStyle($handle . '-editor', $source . 'editor.css')) {
            $metadata['editorStyle'] = $handle . '-editor';
        }

        if (Assets::registerModule($handle . '-view', $source . 'view.js')) {
            $metadata['viewScriptModule'] = $handle . '-view';
        }

        return $metadata;
    }

    /**
     * @param array<string, string[]> $locations
     * @return array<string, string[]>
     */
    public function twigNamespace(array $locations): array
    {
        $locations['blocks'][] = Theme::path(self::DIRECTORY);

        return $locations;
    }
}
