<?php

declare(strict_types=1);

namespace Understack\Context;

use Timber\Timber;
use Understack\Services\SiteOptions;
use Understack\Support\Registrable;

/**
 * Data available to every Twig template.
 */
final class GlobalContext implements Registrable
{
    public function register(): void
    {
        add_filter('timber/context', [$this, 'extend']);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function extend(array $context): array
    {
        $context['menus'] = [
            'primary' => Timber::get_menu_by('location', 'primary') ?? Timber::get_pages_menu(),
            'footer' => Timber::get_menu_by('location', 'footer'),
        ];

        $context['options'] = (new SiteOptions())->all();

        return $context;
    }
}
