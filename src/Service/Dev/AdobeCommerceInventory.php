<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Dev;

final class AdobeCommerceInventory
{
    public const string MARKER_MODULE = 'Magento_Enterprise';

    public const array FAMILIES = [
        'Advanced Checkout' => [
            'ee' => ['Magento_AdvancedCheckout'],
            'obsidian' => 'MageObsidianCommerce_AdvancedCheckout',
        ],
        'Async Checkout' => [
            'ee' => ['Magento_AsyncOrder'],
            'obsidian' => 'MageObsidianCommerce_AsyncOrder',
        ],
        'Catalog Events' => [
            'ee' => ['Magento_CatalogEvent'],
            'obsidian' => 'MageObsidianCommerce_CatalogEvent',
        ],
        'Catalog Permissions' => [
            'ee' => ['Magento_CatalogPermissions'],
            'obsidian' => 'MageObsidianCommerce_CatalogPermissions',
        ],
        'Checkout Address Search' => [
            'ee' => ['Magento_CheckoutAddressSearch'],
            'obsidian' => 'MageObsidianCommerce_CheckoutAddressSearch',
        ],
        'CMS Hierarchy' => [
            'ee' => ['Magento_VersionsCms'],
            'obsidian' => 'MageObsidianCommerce_VersionsCms',
        ],
        'Customer Custom Attributes' => [
            'ee' => ['Magento_CustomerCustomAttributes'],
            'obsidian' => 'MageObsidianCommerce_CustomerCustomAttributes',
        ],
        'Customer Segment' => [
            'ee' => ['Magento_CustomerSegment'],
            'obsidian' => 'MageObsidianCommerce_CustomerSegment',
        ],
        'Dynamic Blocks' => [
            'ee' => ['Magento_Banner'],
            'obsidian' => 'MageObsidianCommerce_Banner',
        ],
        'Gift Card' => [
            'ee' => ['Magento_GiftCard', 'Magento_GiftCardAccount'],
            'obsidian' => 'MageObsidianCommerce_GiftCard',
        ],
        'Gift Registry' => [
            'ee' => ['Magento_GiftRegistry'],
            'obsidian' => 'MageObsidianCommerce_GiftRegistry',
        ],
        'Gift Wrapping' => [
            'ee' => ['Magento_GiftWrapping'],
            'obsidian' => 'MageObsidianCommerce_GiftWrapping',
        ],
        'Invitation' => [
            'ee' => ['Magento_Invitation'],
            'obsidian' => 'MageObsidianCommerce_Invitation',
        ],
        'Multicoupon' => [
            'ee' => ['Magento_Multicoupon'],
            'obsidian' => 'MageObsidianCommerce_Multicoupon',
        ],
        'Multiple Wishlist' => [
            'ee' => ['Magento_MultipleWishlist'],
            'obsidian' => 'MageObsidianCommerce_MultipleWishlist',
        ],
        'Reward Points' => [
            'ee' => ['Magento_Reward'],
            'obsidian' => 'MageObsidianCommerce_Reward',
        ],
        'RMA' => [
            'ee' => ['Magento_Rma'],
            'obsidian' => 'MageObsidianCommerce_Rma',
        ],
        'Social Login' => [
            'ee' => ['Magento_SocialLogin'],
            'obsidian' => 'MageObsidianCommerce_SocialLogin',
        ],
        'Store Credit' => [
            'ee' => ['Magento_CustomerBalance'],
            'obsidian' => 'MageObsidianCommerce_CustomerBalance',
        ],
        'Target Rule' => [
            'ee' => ['Magento_TargetRule'],
            'obsidian' => 'MageObsidianCommerce_TargetRule',
        ],
        'Website Restriction' => [
            'ee' => ['Magento_WebsiteRestriction'],
            'obsidian' => 'MageObsidianCommerce_WebsiteRestriction',
        ],
    ];

    public function isAdobeCommerce(array $enabledModules): bool
    {
        return in_array(self::MARKER_MODULE, $enabledModules, true);
    }

    public function inventory(array $enabledModules): array
    {
        if (!$this->isAdobeCommerce($enabledModules)) {
            return [];
        }

        $result = [];
        foreach (self::FAMILIES as $family => $definition) {
            $active = array_values(array_intersect($definition['ee'], $enabledModules));
            if ($active === []) {
                continue;
            }
            $result[] = [
                'family' => $family,
                'modules' => $active,
                'covered' => in_array($definition['obsidian'], $enabledModules, true),
                'obsidian' => $definition['obsidian'],
            ];
        }

        return $result;
    }
}
