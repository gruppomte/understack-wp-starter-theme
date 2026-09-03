<?php

declare(strict_types=1);

namespace Understack\Support;

interface Registrable
{
    /**
     * Attach WordPress hooks. Called once during theme boot.
     */
    public function register(): void;
}
