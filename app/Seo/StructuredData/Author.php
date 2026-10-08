<?php

declare(strict_types=1);

namespace Understack\Seo\StructuredData;

use Understack\Seo\Text;

/**
 * Author identity allowed in structured data. Only a public, trustworthy
 * display name qualifies: never an e-mail address, a login, a nicename or the
 * local part of the account e-mail, and nothing is ever derived from them.
 */
final class Author
{
    /**
     * @param array{
     *     id?: int|string,
     *     display_name?: string,
     *     user_login?: string,
     *     user_nicename?: string,
     *     user_email?: string,
     *     description?: string,
     *     user_url?: string
     * } $user
     * @return array{id: int, name: string, description: string, url: string}|null
     */
    public static function fromUser(array $user): ?array
    {
        $name = trim((string) ($user['display_name'] ?? ''));

        if (! self::isPublicName($name, $user)) {
            return null;
        }

        $url = trim((string) ($user['user_url'] ?? ''));

        return [
            'id' => (int) ($user['id'] ?? 0),
            'name' => $name,
            'description' => Text::summary((string) ($user['description'] ?? ''), 300),
            'url' => self::isWebUrl($url) ? $url : '',
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function isPublicName(string $name, array $user = []): bool
    {
        if ($name === '' || mb_strlen($name) < 2 || preg_match('/\p{L}.*\p{L}/u', $name) !== 1) {
            return false;
        }

        if (preg_match('/[@<>\/\\\\]|https?:/i', $name) === 1) {
            return false;
        }

        $login = trim((string) ($user['user_login'] ?? ''));
        $nicename = trim((string) ($user['user_nicename'] ?? ''));
        $email = trim((string) ($user['user_email'] ?? ''));
        $localPart = $email !== '' && str_contains($email, '@') ? strstr($email, '@', true) : '';

        foreach ([$login, $nicename, $localPart, $email] as $technical) {
            if ($technical !== '' && $technical !== false && strcasecmp($name, $technical) === 0) {
                return false;
            }
        }

        return true;
    }

    private static function isWebUrl(string $url): bool
    {
        return $url !== ''
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $url) === 1;
    }
}
