<?php

declare(strict_types=1);

namespace Understack\Services;

/**
 * Site-wide editable content stored on the ACF "Site Settings" options page.
 * Values are sanitized here so templates can use them directly.
 */
final class SiteOptions
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{
     *     header_cta: array{url: string, title: string, target: string}|null,
     *     footer_text: string,
     *     social_links: array<int, array{url: string, label: string}>
     * }
     */
    public function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (! function_exists('get_field')) {
            return self::$cache = [
                'header_cta' => null,
                'footer_text' => '',
                'social_links' => [],
            ];
        }

        $footerText = get_field('footer_text', 'option');
        $socialLinks = get_field('social_links', 'option');

        return self::$cache = [
            'header_cta' => $this->link(get_field('header_cta', 'option')),
            'footer_text' => is_string($footerText) ? wp_kses_post($footerText) : '',
            'social_links' => $this->socialLinks(is_array($socialLinks) ? $socialLinks : []),
        ];
    }

    /**
     * @return array{url: string, title: string, target: string}|null
     */
    private function link(mixed $value): ?array
    {
        if (! is_array($value) || empty($value['url'])) {
            return null;
        }

        return [
            'url' => esc_url((string) $value['url']),
            'title' => sanitize_text_field((string) ($value['title'] ?? '')),
            'target' => ($value['target'] ?? '') === '_blank' ? '_blank' : '',
        ];
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array{url: string, label: string}>
     */
    private function socialLinks(array $rows): array
    {
        $links = [];

        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['url'])) {
                continue;
            }

            $links[] = [
                'url' => esc_url((string) $row['url']),
                'label' => sanitize_text_field((string) ($row['label'] ?? '')),
            ];
        }

        return $links;
    }
}
