<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service;

use MageObsidian\ModernFrontend\Service\EagerIslandRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Pure registry; runs on the standalone bootstrap (no Magento types).
 */
class EagerIslandRegistryTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $this->assertSame([], (new EagerIslandRegistry())->all());
    }

    public function testRecordsAndDeduplicates(): void
    {
        $registry = new EagerIslandRegistry();
        $registry->register('Vendor/components/CartCount.js');
        $registry->register('Vendor/components/WishlistCount.js');
        $registry->register('Vendor/components/CartCount.js');

        $this->assertSame(
            ['Vendor/components/CartCount.js', 'Vendor/components/WishlistCount.js'],
            $registry->all()
        );
    }
}
