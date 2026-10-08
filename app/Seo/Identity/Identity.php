<?php

declare(strict_types=1);

namespace Understack\Seo\Identity;

use Understack\Seo\Text;

/**
 * The organisation behind the site, as configured by the client. Plain value
 * object built from an array (ACF today, any other source tomorrow); every
 * value is validated and anything invalid is dropped and reported.
 */
final class Identity
{
    public const MINIMUM_LOGO_SIZE = 112;

    /**
     * @param array{url: string, width: int, height: int}|array{} $logo
     * @param array{street: string, locality: string, region: string, postalCode: string, country: string} $address
     * @param string[] $areasServed
     * @param string[] $sameAs
     * @param string[] $issues
     */
    public function __construct(
        public readonly string $type = '',
        public readonly string $name = '',
        public readonly string $legalName = '',
        public readonly string $description = '',
        public readonly array $logo = [],
        public readonly string $url = '',
        public readonly string $email = '',
        public readonly string $telephone = '',
        public readonly array $address = [
            'street' => '',
            'locality' => '',
            'region' => '',
            'postalCode' => '',
            'country' => '',
        ],
        public readonly array $areasServed = [],
        public readonly array $sameAs = [],
        public readonly array $issues = [],
    ) {
    }

    /**
     * @param array<string, mixed> $raw Keys: type, name, legal_name, description, logo
     *     (array with url, width, height), url, email, telephone, address (street,
     *     locality, region, postal_code, country), areas_served (list), same_as (list).
     * @param string[] $allowedTypes
     */
    public static function fromArray(array $raw, array $allowedTypes = []): self
    {
        $allowedTypes = $allowedTypes === [] ? Types::all() : $allowedTypes;
        $issues = [];
        $text = static fn (mixed $value, int $max): string
            => mb_substr(Text::plain(is_scalar($value) ? (string) $value : ''), 0, $max);

        $type = trim((string) ($raw['type'] ?? ''));

        if ($type !== '' && ! in_array($type, $allowedTypes, true)) {
            $issues[] = sprintf('Unknown organisation type "%s"; no Organization node is produced.', $type);
            $type = '';
        }

        $name = $text($raw['name'] ?? '', 200);
        $url = self::url($raw['url'] ?? '');

        if (($raw['url'] ?? '') !== '' && $url === '') {
            $issues[] = 'The organisation URL is not a valid web address and was ignored.';
        }

        $email = trim((string) ($raw['email'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $issues[] = 'The public e-mail address is not valid and was ignored.';
            $email = '';
        }

        $telephone = trim((string) ($raw['telephone'] ?? ''));

        if ($telephone !== '' && ! self::isTelephone($telephone)) {
            $issues[] = 'The public telephone number is not valid and was ignored.';
            $telephone = '';
        }

        $logo = self::logo($raw['logo'] ?? null, $issues);
        $rawAddress = is_array($raw['address'] ?? null) ? $raw['address'] : [];
        $country = strtoupper(trim((string) ($rawAddress['country'] ?? '')));

        if ($country !== '' && preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            $issues[] = 'The country must be a two-letter ISO 3166-1 code; it was ignored.';
            $country = '';
        }

        $address = [
            'street' => $text($rawAddress['street'] ?? '', 120),
            'locality' => $text($rawAddress['locality'] ?? '', 120),
            'region' => $text($rawAddress['region'] ?? '', 120),
            'postalCode' => $text($rawAddress['postal_code'] ?? ($rawAddress['postalCode'] ?? ''), 20),
            'country' => $country,
        ];

        $areas = [];

        foreach (self::list($raw['areas_served'] ?? []) as $area) {
            $area = $text($area, 120);

            if ($area !== '' && ! in_array($area, $areas, true) && count($areas) < 50) {
                $areas[] = $area;
            }
        }

        $sameAs = [];

        foreach (self::list($raw['same_as'] ?? []) as $candidate) {
            $valid = self::url($candidate);

            if ($valid === '') {
                $issues[] = sprintf(
                    'The profile URL "%s" is not valid and was ignored.',
                    mb_substr(trim((string) $candidate), 0, 80)
                );
            } elseif (! in_array($valid, $sameAs, true)) {
                $sameAs[] = $valid;
            }
        }

        $identity = new self(
            $type,
            $name,
            $text($raw['legal_name'] ?? ($raw['legalName'] ?? ''), 200),
            Text::summary(is_scalar($raw['description'] ?? '') ? (string) $raw['description'] : '', 500),
            $logo,
            $url,
            $email,
            $telephone,
            $address,
            $areas,
            $sameAs,
            []
        );

        if ($type !== '' && $name === '') {
            $issues[] = 'The organisation name is missing: no Organization node is produced.';
        }

        if ($type !== '' && $name !== '' && Types::isLocalBusiness($type) && ! $identity->hasPostalAddress()) {
            $issues[] = sprintf(
                '%s needs a street, a locality and a country; Organization is used until the address is complete.',
                $type
            );
        }

        return new self(
            $identity->type,
            $identity->name,
            $identity->legalName,
            $identity->description,
            $identity->logo,
            $identity->url,
            $identity->email,
            $identity->telephone,
            $identity->address,
            $identity->areasServed,
            $identity->sameAs,
            $issues
        );
    }

    /**
     * An Organization node can be produced.
     */
    public function isConfigured(): bool
    {
        return $this->type !== '' && $this->name !== '';
    }

    /**
     * The type actually emitted: a LocalBusiness subtype without a complete
     * postal address falls back to Organization.
     */
    public function schemaType(): string
    {
        if (! $this->isConfigured()) {
            return '';
        }

        return Types::isLocalBusiness($this->type) && ! $this->hasPostalAddress() ? 'Organization' : $this->type;
    }

    public function hasPostalAddress(): bool
    {
        return $this->address['street'] !== ''
            && $this->address['locality'] !== ''
            && $this->address['country'] !== '';
    }

    public function hasAnyAddress(): bool
    {
        return implode('', $this->address) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'schema_type' => $this->schemaType(),
            'name' => $this->name,
            'legal_name' => $this->legalName,
            'description' => $this->description,
            'logo' => $this->logo,
            'url' => $this->url,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'address' => $this->address,
            'areas_served' => $this->areasServed,
            'same_as' => $this->sameAs,
            'issues' => $this->issues,
        ];
    }

    private static function url(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        if (preg_match('#^https?://#i', $value) !== 1) {
            return '';
        }

        return $value;
    }

    /**
     * @return array<int, mixed>
     */
    private static function list(mixed $value): array
    {
        if (is_string($value)) {
            return preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        return is_array($value) ? array_values($value) : [];
    }

    private static function isTelephone(string $value): bool
    {
        return preg_match('/^\+?[0-9][0-9 ().\/-]{4,30}$/', $value) === 1
            && preg_match_all('/[0-9]/', $value) >= 6;
    }

    /**
     * @param string[] $issues
     * @return array{url: string, width: int, height: int}|array{}
     */
    private static function logo(mixed $value, array &$issues): array
    {
        if (! is_array($value)) {
            return [];
        }

        $url = self::url($value['url'] ?? '');
        $width = (int) ($value['width'] ?? 0);
        $height = (int) ($value['height'] ?? 0);

        if ($url === '') {
            $issues[] = 'The logo has no valid URL and was ignored.';

            return [];
        }

        if ($width > 0 && $height > 0 && ($width < self::MINIMUM_LOGO_SIZE || $height < self::MINIMUM_LOGO_SIZE)) {
            $issues[] = sprintf(
                'The logo is %1$dx%2$d px; Google asks for at least %3$dx%3$d px for the Organization logo.',
                $width,
                $height,
                self::MINIMUM_LOGO_SIZE
            );
        }

        return ['url' => $url, 'width' => $width, 'height' => $height];
    }
}
