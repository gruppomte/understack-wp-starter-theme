<?php

declare(strict_types=1);

namespace Understack\Seo;

enum PageKind: string
{
    case FrontPage = 'front_page';
    case Home = 'home';
    case Singular = 'singular';
    case Term = 'term';
    case Author = 'author';
    case Date = 'date';
    case PostTypeArchive = 'post_type_archive';
    case Search = 'search';
    case NotFound = 'not_found';
    case Other = 'other';
}
