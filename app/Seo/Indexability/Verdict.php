<?php

declare(strict_types=1);

namespace Understack\Seo\Indexability;

/**
 * Result of the environment policy for the current site.
 */
final class Verdict
{
    /**
     * @param string[] $reasons
     * @param string[] $warnings
     */
    public function __construct(
        public readonly bool $noindex,
        public readonly string $type,
        public readonly bool $declared,
        public readonly string $host,
        public readonly array $reasons = [],
        public readonly array $warnings = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'declared' => $this->declared,
            'host' => $this->host,
            'noindex' => $this->noindex,
            'reasons' => $this->reasons,
            'warnings' => $this->warnings,
        ];
    }
}
