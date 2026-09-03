<?php

declare(strict_types=1);

namespace Understack\Core;

use Timber\Timber as TimberEngine;
use Twig\Environment;
use Twig\TwigFunction;
use Understack\Support\Environment as ThemeEnvironment;
use Understack\Support\Image;
use Understack\Support\Registrable;

final class Timber implements Registrable
{
    public function register(): void
    {
        TimberEngine::init();
        TimberEngine::$dirname = ['views'];

        add_filter('timber/twig', [$this, 'extend']);
        add_filter('timber/twig/environment/options', [$this, 'environmentOptions']);
    }

    public function extend(Environment $twig): Environment
    {
        $twig->addFunction(new TwigFunction('asset', [Assets::class, 'url']));
        $twig->addFunction(new TwigFunction('attachment_image', [Image::class, 'render'], ['is_safe' => ['html']]));

        return $twig;
    }

    /**
     * Compiled templates are cached outside local environments; auto_reload keeps
     * the cache valid after deployments without manual purging.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function environmentOptions(array $options): array
    {
        if (ThemeEnvironment::isLocal()) {
            return $options;
        }

        $cache = $this->cacheDirectory();

        if ($cache !== null) {
            $options['cache'] = $cache;
            $options['auto_reload'] = true;
        }

        return $options;
    }

    private function cacheDirectory(): ?string
    {
        $directory = WP_CONTENT_DIR . '/cache/' . Theme::SLUG . '/twig';

        if (wp_mkdir_p($directory) && wp_is_writable($directory)) {
            return $directory;
        }

        return null;
    }
}
