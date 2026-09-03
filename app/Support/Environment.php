<?php

declare(strict_types=1);

namespace Understack\Support;

final class Environment
{
    public static function isLocal(): bool
    {
        return in_array(wp_get_environment_type(), ['local', 'development'], true);
    }

    public static function isProduction(): bool
    {
        return wp_get_environment_type() === 'production';
    }
}
