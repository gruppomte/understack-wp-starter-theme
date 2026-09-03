<?php

declare(strict_types=1);

namespace Understack\Controllers;

/**
 * Renders WooCommerce pages inside the theme layout. WooCommerce loads
 * woocommerce.php from the theme root, which delegates here.
 */
final class WooCommerceController extends Controller
{
    public function handle(): void
    {
        $this->render(['woocommerce/shop.twig'], $this->context());
    }
}
