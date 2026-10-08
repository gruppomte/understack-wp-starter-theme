<?php

declare(strict_types=1);

namespace Understack\Seo\Identity;

use Understack\Core\Theme;

/**
 * Reads the identity fields of the ACF "Site Settings" options page straight
 * from the options table (ACF stores every field as "options_<name>"), priming
 * the option cache once so unset fields cost no query, and lets a companion
 * plugin replace or complete them through the "<slug>/seo/identity" filter.
 * ACF itself is never called: the data model does not depend on it.
 */
final class Repository
{
    private const PREFIX = 'options_';

    /** @var string[] Single-value fields. */
    private const FIELDS = [
        'org_type',
        'org_name',
        'org_legal_name',
        'org_description',
        'org_logo',
        'org_url',
        'org_email',
        'org_telephone',
        'org_address_street',
        'org_address_locality',
        'org_address_region',
        'org_address_postal_code',
        'org_address_country',
    ];

    /** @var array<string, string[]> Repeater fields and their sub fields. */
    private const REPEATERS = [
        'social_links' => ['url', 'label'],
        'org_areas_served' => ['area'],
        'org_same_as' => ['url'],
    ];

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
        self::prime();

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
            'type' => self::option('org_type'),
            'name' => self::option('org_name'),
            'legal_name' => self::option('org_legal_name'),
            'description' => self::option('org_description'),
            'logo' => self::logo(self::option('org_logo')),
            'url' => self::option('org_url'),
            'email' => self::option('org_email'),
            'telephone' => self::option('org_telephone'),
            'address' => [
                'street' => self::option('org_address_street'),
                'locality' => self::option('org_address_locality'),
                'region' => self::option('org_address_region'),
                'postal_code' => self::option('org_address_postal_code'),
                'country' => self::option('org_address_country'),
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

    /**
     * Loads every option the identity needs with one query (two with repeater
     * rows) and records the missing ones, so unset fields never query again.
     */
    private static function prime(): void
    {
        if (! function_exists('wp_prime_option_caches')) {
            return;
        }

        $names = array_map(static fn (string $field): string => self::PREFIX . $field, self::FIELDS);

        foreach (array_keys(self::REPEATERS) as $repeater) {
            $names[] = self::PREFIX . $repeater;
        }

        wp_prime_option_caches($names);

        $rowNames = [];

        foreach (self::REPEATERS as $repeater => $subFields) {
            $count = (int) get_option(self::PREFIX . $repeater);

            for ($index = 0; $index < min($count, 100); $index++) {
                foreach ($subFields as $subField) {
                    $rowNames[] = self::PREFIX . $repeater . '_' . $index . '_' . $subField;
                }
            }
        }

        if ($rowNames !== []) {
            wp_prime_option_caches($rowNames);
        }
    }

    private static function option(string $field): mixed
    {
        $value = get_option(self::PREFIX . $field);

        return $value === false ? '' : $value;
    }

    /**
     * Repeater rows: the row count is stored under the field name, each value
     * under "<field>_<row>_<sub field>".
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rows(string $repeater): array
    {
        $count = min((int) get_option(self::PREFIX . $repeater), 100);
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $row = [];

            foreach (self::REPEATERS[$repeater] ?? [] as $subField) {
                $value = get_option(self::PREFIX . $repeater . '_' . $index . '_' . $subField);

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
