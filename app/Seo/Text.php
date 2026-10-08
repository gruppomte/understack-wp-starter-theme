<?php

declare(strict_types=1);

namespace Understack\Seo;

/**
 * Plain-text helpers used for descriptions and social metadata. Pure functions
 * (no WordPress dependency) so they stay unit-testable.
 */
final class Text
{
    /**
     * HTML to normalised plain text: comments (block delimiters), scripts, styles
     * and tags removed, entities decoded, whitespace collapsed.
     */
    public static function plain(string $html): string
    {
        $text = preg_replace('/<!--.*?-->/s', ' ', $html) ?? '';
        $text = preg_replace('#<(script|style|template|noscript|svg)\b[^>]*>.*?</\1\s*>#is', ' ', $text) ?? '';
        $text = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>#i', ' ', $text) ?? '';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure helper, no WordPress.
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}\x{200B}]+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * Shortens plain text to at most $max characters without cutting a word:
     * at a sentence end when one falls late enough, otherwise at a word
     * boundary followed by an ellipsis.
     */
    public static function summary(string $text, int $max = 160): string
    {
        $text = self::plain($text);

        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $sentenceEnd = 0;

        foreach (['. ', '! ', '? '] as $end) {
            $position = mb_strrpos($cut, $end);

            if ($position !== false) {
                $sentenceEnd = max($sentenceEnd, $position + 1);
            }
        }

        if ($sentenceEnd >= $max * 0.6) {
            return rtrim(mb_substr($cut, 0, $sentenceEnd));
        }

        $space = mb_strrpos($cut, ' ');
        $cut = $space !== false && $space >= $max * 0.5 ? mb_substr($cut, 0, $space) : mb_substr($cut, 0, $max - 1);

        return rtrim($cut, " \t\n\r,;:-\u{2013}\u{2014}") . "\u{2026}";
    }

    /**
     * Lowercased host name without port, or an empty string.
     */
    public static function host(string $url): string
    {
        // phpcs:disable WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper, no WordPress.
        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $host = parse_url('//' . ltrim(trim($url), '/'), PHP_URL_HOST);
        }
        // phpcs:enable

        return is_string($host) ? strtolower($host) : '';
    }
}
