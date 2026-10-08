<?php

declare(strict_types=1);

namespace Understack\Seo;

use Understack\Core\Theme;

/**
 * Effective SEO configuration: the defaults below, overridden by config/seo.php,
 * then by the "<slug>/seo/config" filter. Every value is validated; an invalid
 * entry keeps its default and is listed in issues() for the diagnostics.
 */
final class Config
{
    public const FILE = 'config/seo.php';

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'enabled' => true,
        'provider' => 'auto',
        'fallbacks' => [
            'description' => true,
            'canonical' => true,
            'social' => true,
        ],
        'with_provider' => [],
        'indexability' => [
            'non_production' => 'noindex',
            'production_hosts' => [],
            'x_robots_tag' => true,
            'not_found' => 'noindex',
            'attachments' => 'noindex',
            'post_types' => [],
            'taxonomies' => [],
        ],
        'sitemap' => [
            'exclude_noindex' => true,
            'users' => false,
        ],
        'social' => [
            'twitter_card' => 'summary_large_image',
            'twitter_site' => '',
            'image_size' => 'wide',
            'default_image' => 0,
        ],
        'content' => [
            'article_post_types' => ['post'],
        ],
    ];

    /** @var array<string, array{0: string, 1?: string[]}> Validation rule per dotted key. */
    private const SCHEMA = [
        'enabled' => ['bool'],
        'provider' => [
            'enum',
            ['auto', 'none', 'rank-math', 'yoast', 'seopress', 'aioseo', 'the-seo-framework', 'slim-seo'],
        ],
        'fallbacks.description' => ['bool'],
        'fallbacks.canonical' => ['bool'],
        'fallbacks.social' => ['bool'],
        'with_provider' => ['enum_list', ['description', 'canonical', 'social']],
        'indexability.non_production' => ['enum', ['noindex', 'off']],
        'indexability.production_hosts' => ['hosts'],
        'indexability.x_robots_tag' => ['bool'],
        'indexability.not_found' => ['enum', ['noindex', 'index']],
        'indexability.attachments' => ['enum', ['noindex', 'index']],
        'indexability.post_types' => ['keys'],
        'indexability.taxonomies' => ['keys'],
        'sitemap.exclude_noindex' => ['bool'],
        'sitemap.users' => ['bool'],
        'social.twitter_card' => ['enum', ['summary', 'summary_large_image']],
        'social.twitter_site' => ['handle'],
        'social.image_size' => ['key'],
        'social.default_image' => ['int'],
        'content.article_post_types' => ['keys'],
    ];

    /**
     * @param array<string, mixed> $values
     * @param string[] $issues
     */
    private function __construct(private readonly array $values, private readonly array $issues)
    {
    }

    /**
     * Defaults, then config/seo.php, then the configuration filter.
     */
    public static function load(): self
    {
        $overrides = [];
        $issues = [];
        $file = Theme::path(self::FILE);

        if (is_file($file)) {
            $loaded = require $file;

            if (is_array($loaded)) {
                $overrides = $loaded;
            } else {
                $issues[] = sprintf('%s must return an array.', self::FILE);
            }
        }

        /**
         * Filters the SEO configuration overrides (same keys as config/seo.php).
         *
         * @param array<string, mixed> $overrides
         */
        $filtered = apply_filters(Theme::SLUG . '/seo/config', $overrides);

        if (is_array($filtered)) {
            $overrides = $filtered;
        } else {
            $issues[] = 'The configuration filter must return an array.';
        }

        return self::fromArray($overrides, $issues);
    }

    /**
     * Validates overrides against the schema. Pure: usable without WordPress.
     *
     * @param array<string, mixed> $overrides
     * @param string[] $issues
     */
    public static function fromArray(array $overrides, array $issues = []): self
    {
        $values = self::DEFAULTS;

        foreach (self::flatten($overrides) as $key => $value) {
            if (! isset(self::SCHEMA[$key])) {
                $issues[] = sprintf('Unknown key "%s" ignored.', $key);
                continue;
            }

            $validated = self::validate($value, self::SCHEMA[$key]);

            if ($validated === null) {
                $issues[] = sprintf('Invalid value for "%s"; the default is used.', $key);
                continue;
            }

            self::set($values, $key, $validated);
        }

        return new self($values, $issues);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function string(string $key): string
    {
        $value = $this->get($key, '');

        return is_scalar($value) ? (string) $value : '';
    }

    public function int(string $key): int
    {
        $value = $this->get($key, 0);

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return string[]
     */
    public function list(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * @return string[]
     */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $flat += self::flatten($value, $path);
                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function set(array &$values, string $key, mixed $value): void
    {
        $target = &$values;

        foreach (explode('.', $key) as $segment) {
            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    /**
     * @param array{0: string, 1?: string[]} $rule
     * @return mixed Null when the value is invalid.
     */
    private static function validate(mixed $value, array $rule): mixed
    {
        $allowed = $rule[1] ?? [];

        return match ($rule[0]) {
            'bool' => is_bool($value) ? $value : null,
            'int' => is_int($value) && $value >= 0 ? $value : null,
            'enum' => is_string($value) && in_array($value, $allowed, true) ? $value : null,
            'enum_list' => self::stringList(
                $value,
                static fn (string $item): ?string => in_array($item, $allowed, true) ? $item : null
            ),
            'keys' => self::stringList($value, [self::class, 'key']),
            'key' => is_string($value) ? self::key($value) : null,
            'hosts' => self::stringList($value, static fn (string $item): ?string => Text::host($item) ?: null),
            'handle' => self::handle($value),
            default => null,
        };
    }

    /**
     * @param callable(string): ?string $normalise
     * @return string[]|null
     */
    private static function stringList(mixed $value, callable $normalise): ?array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        $items = [];

        foreach ($value as $item) {
            $normalised = is_string($item) ? $normalise(trim($item)) : null;

            if ($normalised === null) {
                return null;
            }

            $items[] = $normalised;
        }

        return array_values(array_unique($items));
    }

    private static function key(string $value): ?string
    {
        return preg_match('/^[a-z0-9_-]+$/i', $value) === 1 ? strtolower($value) : null;
    }

    private static function handle(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = ltrim(trim($value), '@');

        if ($value === '') {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_]{1,15}$/', $value) === 1 ? '@' . $value : null;
    }
}
