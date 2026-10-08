<?php

declare(strict_types=1);

namespace Understack\Seo\Identity;

use Understack\Core\Theme;

/**
 * Reads the identity fields from the ACF "Site Settings" options page (with a
 * plain-option fallback when ACF is not loaded) and lets a companion plugin
 * replace or complete them through the "<slug>/seo/identity" filter.
 */
final class Repository
{
    public static function load(): Identity
    {
        /**
         * Filters the accepted Schema.org organisation types.
         *
         * @param string[] $types
         */
        $types = apply_filters(Theme::SLUG . '/seo/identity_types', Types::all());

        $allowed = is_array($types) ? array_values(array_filter($types, 'is_string')) : [];

        return Identity::fromArray(self::raw(), $allowed);
    }

    /**
     * Raw values in the shape Identity::fromArray() expects.
     *
     * @return array<string, mixed>
     */
    public static function raw(): array
    {
        $address = self::field('org_address');
        $address = is_array($address) ? $address : [];
        $sameAs = [];

        foreach (self::rows('social_links') as $row) {
            $sameAs[] = (string) ($row['url'] ?? '');
        }

        foreach (self::rows('org_same_as') as $row) {
            $sameAs[] = (string) ($row['url'] ?? '');
        }

        $areas = [];

        foreach (self::rows('org_areas_served') as $row) {
            $areas[] = (string) ($row['area'] ?? '');
        }

        $raw = [
            'type' => self::field('org_type'),
            'name' => self::field('org_name'),
            'legal_name' => self::field('org_legal_name'),
            'description' => self::field('org_description'),
            'logo' => self::logo(self::field('org_logo')),
            'url' => self::field('org_url'),
            'email' => self::field('org_email'),
            'telephone' => self::field('org_telephone'),
            'address' => [
                'street' => $address['street'] ?? '',
                'locality' => $address['locality'] ?? '',
                'region' => $address['region'] ?? '',
                'postal_code' => $address['postal_code'] ?? '',
                'country' => $address['country'] ?? '',
            ],
            'areas_served' => array_filter($areas, static fn (string $area): bool => $area !== ''),
            'same_as' => array_filter($sameAs, static fn (string $url): bool => $url !== ''),
        ];

        /**
         * Filters the raw organisation identity before validation (same keys as
         * Identity::fromArray()). A companion plugin can provide the data here.
         *
         * @param array<string, mixed> $raw
         */
        $filtered = apply_filters(Theme::SLUG . '/seo/identity', $raw);

        return is_array($filtered) ? $filtered : $raw;
    }

    private static function field(string $name): mixed
    {
        if (function_exists('get_field')) {
            return get_field($name, 'option');
        }

        return get_option('options_' . $name);
    }

    /**
     * Repeater rows without ACF: the row count is stored under the field name,
     * each value under "<field>_<row>_<sub field>".
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rows(string $name): array
    {
        if (function_exists('get_field')) {
            $rows = get_field($name, 'option');

            return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        }

        $count = (int) get_option('options_' . $name);
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $row = [];

            foreach (['url', 'label', 'area'] as $subField) {
                $value = get_option('options_' . $name . '_' . $index . '_' . $subField);

                if (is_string($value)) {
                    $row[$subField] = $value;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array{url: string, width: int, height: int}|null
     */
    private static function logo(mixed $value): ?array
    {
        $id = is_array($value) ? (int) ($value['ID'] ?? $value['id'] ?? 0) : (int) $value;

        if ($id <= 0) {
            return null;
        }

        $source = wp_get_attachment_image_src($id, 'full');

        if (! is_array($source) || ! is_string($source[0]) || $source[0] === '') {
            return null;
        }

        return ['url' => $source[0], 'width' => (int) $source[1], 'height' => (int) $source[2]];
    }
}
