<?php

declare(strict_types=1);

namespace Understack\Core;

use Understack\Controllers\ArchiveController;
use Understack\Controllers\Controller;
use Understack\Controllers\IndexController;
use Understack\Controllers\NotFoundController;
use Understack\Controllers\PageController;
use Understack\Controllers\SearchController;
use Understack\Controllers\SingleController;

/**
 * Maps the current request to a controller, mirroring the WordPress template hierarchy.
 */
final class Router
{
    public static function dispatch(): void
    {
        self::resolve()->handle();
    }

    private static function resolve(): Controller
    {
        return match (true) {
            is_404() => new NotFoundController(),
            is_search() => new SearchController(),
            is_page() => new PageController(),
            is_singular() => new SingleController(),
            is_home() => new IndexController(),
            is_archive() => new ArchiveController(),
            default => new IndexController(),
        };
    }
}
