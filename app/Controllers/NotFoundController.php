<?php

declare(strict_types=1);

namespace Understack\Controllers;

final class NotFoundController extends Controller
{
    public function handle(): void
    {
        $this->render(['system/404.twig'], $this->context());
    }
}
