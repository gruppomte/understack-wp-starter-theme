<?php

declare(strict_types=1);

namespace Understack\Seo\StructuredData;

use Understack\Seo\Identity\Identity;

/**
 * Builds the JSON-LD graph: Organization (when configured), WebSite, the
 * page node, its primary image and breadcrumb, the article and its author.
 * Pure and deterministic: same input, same nodes, stable @id values.
 */
final class Graph
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function build(Input $input): array
    {
        $home = rtrim($input->home, '/') . '/';
        $websiteId = $home . '#website';
        $organizationId = $input->identity instanceof Identity && $input->identity->isConfigured()
            ? $home . '#organization'
            : null;
        $nodes = [];

        if ($organizationId !== null && $input->identity instanceof Identity) {
            $nodes[] = self::organization($input->identity, $organizationId, $home);
        }

        $nodes[] = [
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'url' => $home,
            'name' => $input->siteName,
            'description' => $input->tagline,
            'publisher' => self::reference($organizationId),
            'inLanguage' => $input->language,
        ];

        if ($input->canonical === '') {
            return $nodes;
        }

        $pageId = $input->canonical . '#webpage';
        $imageId = $input->image !== null ? $input->canonical . '#primaryimage' : null;
        $breadcrumbId = count($input->breadcrumbs) > 1 ? $input->canonical . '#breadcrumb' : null;
        $personId = null;

        if ($imageId !== null && $input->image !== null) {
            $nodes[] = [
                '@type' => 'ImageObject',
                '@id' => $imageId,
                'url' => $input->image['url'],
                'contentUrl' => $input->image['url'],
                'width' => $input->image['width'] > 0 ? $input->image['width'] : null,
                'height' => $input->image['height'] > 0 ? $input->image['height'] : null,
                'caption' => $input->image['alt'],
                'inLanguage' => $input->language,
            ];
        }

        if ($breadcrumbId !== null) {
            $nodes[] = self::breadcrumb($input->breadcrumbs, $breadcrumbId);
        }

        $person = $input->pageType === 'ProfilePage' ? $input->profile : $input->author;

        if ($person !== null) {
            $personId = $home . '#/schema/person/' . substr(md5('person-' . $person['id']), 0, 12);
            $nodes[] = [
                '@type' => 'Person',
                '@id' => $personId,
                'name' => $person['name'],
                'description' => $person['description'],
                'url' => $input->pageType === 'ProfilePage' ? $input->profileUrl : $input->authorUrl,
                'sameAs' => $person['url'] !== '' ? [$person['url']] : [],
            ];
        }

        $nodes[] = [
            '@type' => $input->pageType,
            '@id' => $pageId,
            'url' => $input->canonical,
            'name' => $input->title,
            'isPartOf' => self::reference($websiteId),
            'description' => $input->description,
            'inLanguage' => $input->language,
            'datePublished' => $input->datePublished,
            'dateModified' => $input->dateModified,
            'primaryImageOfPage' => self::reference($imageId),
            'breadcrumb' => self::reference($breadcrumbId),
            'about' => $input->pageType === 'AboutPage' ? self::reference($organizationId) : null,
            'mainEntity' => $input->pageType === 'ProfilePage' ? self::reference($personId) : null,
        ];

        if ($input->article !== null) {
            $nodes[] = [
                '@type' => $input->article['type'],
                '@id' => $input->canonical . '#article',
                'headline' => $input->article['headline'],
                'description' => $input->description,
                'url' => $input->canonical,
                'mainEntityOfPage' => self::reference($pageId),
                'isPartOf' => self::reference($pageId),
                'datePublished' => $input->article['published'],
                'dateModified' => $input->article['modified'],
                'author' => $input->pageType !== 'ProfilePage' ? self::reference($personId) : null,
                'publisher' => self::reference($organizationId),
                'image' => self::reference($imageId),
                'articleSection' => $input->article['sections'],
                'wordCount' => $input->article['wordCount'] > 0 ? $input->article['wordCount'] : null,
                'inLanguage' => $input->language,
            ];
        }

        return $nodes;
    }

    /**
     * Every @id referenced by a node must be declared by a node of the graph.
     *
     * @param array<int, array<string, mixed>> $nodes
     * @return string[] Dangling references.
     */
    public static function danglingReferences(array $nodes): array
    {
        $declared = [];
        $referenced = [];

        foreach ($nodes as $node) {
            self::walk($node, $declared, $referenced);
        }

        return array_values(array_unique(array_diff($referenced, $declared)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function organization(Identity $identity, string $id, string $home): array
    {
        $logo = $identity->logo;

        return [
            '@type' => $identity->schemaType(),
            '@id' => $id,
            'name' => $identity->name,
            'legalName' => $identity->legalName !== $identity->name ? $identity->legalName : '',
            'description' => $identity->description,
            'url' => $identity->url !== '' ? $identity->url : $home,
            'logo' => $logo !== [] ? [
                '@type' => 'ImageObject',
                '@id' => $home . '#logo',
                'url' => $logo['url'],
                'contentUrl' => $logo['url'],
                'width' => $logo['width'] > 0 ? $logo['width'] : null,
                'height' => $logo['height'] > 0 ? $logo['height'] : null,
                'caption' => $identity->name,
            ] : null,
            'image' => $logo !== [] ? self::reference($home . '#logo') : null,
            'email' => $identity->email,
            'telephone' => $identity->telephone,
            'address' => $identity->hasAnyAddress() ? [
                '@type' => 'PostalAddress',
                'streetAddress' => $identity->address['street'],
                'addressLocality' => $identity->address['locality'],
                'addressRegion' => $identity->address['region'],
                'postalCode' => $identity->address['postalCode'],
                'addressCountry' => $identity->address['country'],
            ] : null,
            'areaServed' => $identity->areasServed,
            'sameAs' => $identity->sameAs,
        ];
    }

    /**
     * @param array<int, array{name: string, url: string|null}> $items
     * @return array<string, mixed>
     */
    private static function breadcrumb(array $items, string $id): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return ['@type' => 'BreadcrumbList', '@id' => $id, 'itemListElement' => $elements];
    }

    /**
     * @return array{'@id': string}|null
     */
    private static function reference(?string $id): ?array
    {
        return $id !== null && $id !== '' ? ['@id' => $id] : null;
    }

    /**
     * Collects declared identifiers (objects with properties besides @id) and
     * references (objects made of @id only), recursively.
     *
     * @param string[] $declared
     * @param string[] $referenced
     */
    private static function walk(mixed $value, array &$declared, array &$referenced): void
    {
        if (! is_array($value)) {
            return;
        }

        if (isset($value['@id']) && is_string($value['@id'])) {
            if (count($value) === 1) {
                $referenced[] = $value['@id'];

                return;
            }

            $declared[] = $value['@id'];
        }

        foreach ($value as $key => $item) {
            if ($key !== '@id') {
                self::walk($item, $declared, $referenced);
            }
        }
    }
}
