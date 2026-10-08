<?php

declare(strict_types=1);

namespace Understack\Seo\StructuredData;

/**
 * JSON-LD serialisation: empty values pruned, HTML-safe encoding so the
 * payload can never close its own script element.
 */
final class Serializer
{
    public const FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT;

    /**
     * Removes null, empty strings and empty arrays recursively; keeps zero and false.
     */
    public static function prune(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $pruned = [];

        foreach ($value as $key => $item) {
            $item = self::prune($item);

            if ($item === null || $item === '' || $item === []) {
                continue;
            }

            $pruned[$key] = $item;
        }

        return array_is_list($value) ? array_values($pruned) : $pruned;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    public static function encode(array $nodes): string
    {
        $graph = self::prune(['@context' => 'https://schema.org', '@graph' => array_values($nodes)]);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- same flags as wp_json_encode, usable without WordPress.
        $json = json_encode($graph, self::FLAGS);

        return is_string($json) ? $json : '';
    }

    public static function script(string $json): string
    {
        return $json === '' ? '' : '<script type="application/ld+json">' . $json . '</script>';
    }
}
