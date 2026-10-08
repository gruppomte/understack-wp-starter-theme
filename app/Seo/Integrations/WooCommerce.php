<?php

declare(strict_types=1);

namespace Understack\Seo\Integrations;

/**
 * Compatibility with WooCommerce: the plugin owns Product and Offer data and
 * prints its own structured data on shop pages, so the theme emits no graph
 * and no breadcrumb trail there; cart, checkout and account pages are
 * transactional (noindex by WooCommerce) and get nothing from the theme.
 */
final class WooCommerce
{
    public static function isActive(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Product singles, the shop page and product taxonomies.
     */
    public static function isShopPage(): bool
    {
        return function_exists('is_woocommerce') && is_woocommerce();
    }

    /**
     * Cart, checkout, order and account pages.
     */
    public static function isTransactionalPage(): bool
    {
        foreach (['is_cart', 'is_checkout', 'is_account_page'] as $conditional) {
            if (function_exists($conditional) && $conditional()) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the theme emits on a page, given its WooCommerce role. Pure.
     *
     * @return array{description: bool, social: bool, graph: bool, breadcrumbs: bool}
     */
    public static function allowances(bool $shopPage, bool $transactionalPage): array
    {
        if ($transactionalPage) {
            return ['description' => false, 'social' => false, 'graph' => false, 'breadcrumbs' => false];
        }

        if ($shopPage) {
            return ['description' => true, 'social' => true, 'graph' => false, 'breadcrumbs' => false];
        }

        return ['description' => true, 'social' => true, 'graph' => true, 'breadcrumbs' => true];
    }

    /**
     * @return array{active: bool, version: string}
     */
    public static function facts(): array
    {
        return [
            'active' => self::isActive(),
            'version' => defined('WC_VERSION') ? (string) constant('WC_VERSION') : '',
        ];
    }
}
