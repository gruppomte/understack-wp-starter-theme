<?php

declare(strict_types=1);

namespace Understack\Seo;

/**
 * Head and crawl signals whose ownership is resolved per request.
 */
enum Signal: string
{
    case Title = 'title';
    case Description = 'description';
    case Canonical = 'canonical';
    case Robots = 'robots';
    case Social = 'social';
    case Sitemap = 'sitemap';
    case RobotsTxt = 'robots_txt';
}
