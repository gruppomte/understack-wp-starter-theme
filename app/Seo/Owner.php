<?php

declare(strict_types=1);

namespace Understack\Seo;

enum Owner: string
{
    case Core = 'core';
    case Provider = 'provider';
    case Theme = 'theme';
    case None = 'none';
}
