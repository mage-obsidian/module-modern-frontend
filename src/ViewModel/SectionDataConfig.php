<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Runtime config for the JS section store (customer-data) invalidation backstop.
 *
 * The client mirrors Magento's private content (cart, customer, …) into
 * localStorage and only re-fetches it when the cache is empty or the
 * `private_content_version` cookie moves. That cookie moves on POST only, so a
 * section whose server data changed through a non-POST path the browser took —
 * most commonly the PHP session/quote expiring while localStorage lives on —
 * would otherwise stay stale (e.g. a cart badge outliving its quote). Publishing
 * a per-section lifetime lets the client age such a section out and reload it, as
 * Magento's native `customer-data.js` does.
 *
 * The lifetime is Magento's own admin setting
 * (`customer/online_customers/section_data_lifetime`, in minutes); the expirable
 * set mirrors Magento_Customer's `di.xml` default. Consumed by the JS bridge as
 * `window.__MAGE_OBSIDIAN_SECTIONS__`.
 */
class SectionDataConfig implements ArgumentInterface
{
    /** Admin setting (minutes) controlling how long a section stays fresh. */
    private const LIFETIME_CONFIG_PATH = 'customer/online_customers/section_data_lifetime';

    /** Sections invalidated by lifetime; mirrors Magento_Customer's di.xml default. */
    private const EXPIRABLE_SECTIONS = ['cart'];

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * The runtime config the page publishes to the JS section store.
     *
     * @return array{lifetime: int, expirable: string[]}
     */
    public function getConfig(): array
    {
        return [
            'lifetime' => $this->lifetimeSeconds(),
            'expirable' => self::EXPIRABLE_SECTIONS,
        ];
    }

    /**
     * Section lifetime in seconds (admin stores minutes; 0 disables the backstop).
     *
     * @return int
     */
    private function lifetimeSeconds(): int
    {
        $minutes = (int)$this->scopeConfig->getValue(
            self::LIFETIME_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );

        return max(0, $minutes) * 60;
    }
}
