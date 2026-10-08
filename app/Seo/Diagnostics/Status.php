<?php

declare(strict_types=1);

namespace Understack\Seo\Diagnostics;

/**
 * Outcome of a diagnostic check. NOT VERIFIED means the theme cannot measure
 * it from inside WordPress (server, CDN, search engines, third parties).
 */
enum Status: string
{
    case Pass = 'PASS';
    case Warning = 'WARNING';
    case Fail = 'FAIL';
    case NotVerified = 'NOT VERIFIED';
}
