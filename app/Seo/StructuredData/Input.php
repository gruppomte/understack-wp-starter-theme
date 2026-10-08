<?php

declare(strict_types=1);

namespace Understack\Seo\StructuredData;

use Understack\Seo\Identity\Identity;

/**
 * Everything the graph builder needs, collected once from WordPress (or built
 * directly in tests). Plain data only.
 */
final class Input
{
    /**
     * @param array{
     *     type: string,
     *     headline: string,
     *     published: string,
     *     modified: string,
     *     sections: string[],
     *     wordCount: int
     * }|null $article
     * @param array{id: int, name: string, description: string, url: string}|null $author
     * @param array{url: string, width: int, height: int, alt: string}|null $image
     * @param array<int, array{name: string, url: string|null}> $breadcrumbs
     * @param array{id: int, name: string, description: string, url: string}|null $profile Person of an author archive.
     */
    public function __construct(
        public readonly string $home,
        public readonly string $siteName,
        public readonly string $language = '',
        public readonly string $tagline = '',
        public readonly ?Identity $identity = null,
        public readonly string $canonical = '',
        public readonly string $pageType = 'WebPage',
        public readonly string $title = '',
        public readonly string $description = '',
        public readonly string $datePublished = '',
        public readonly string $dateModified = '',
        public readonly ?array $article = null,
        public readonly ?array $author = null,
        public readonly string $authorUrl = '',
        public readonly ?array $image = null,
        public readonly array $breadcrumbs = [],
        public readonly ?array $profile = null,
        public readonly string $profileUrl = '',
    ) {
    }
}
