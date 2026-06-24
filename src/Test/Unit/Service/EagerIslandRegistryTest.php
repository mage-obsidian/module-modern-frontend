<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service;

use MageObsidian\ModernFrontend\Service\EagerIslandRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Pure dedup tracker; runs on the standalone bootstrap (no Magento types).
 */
class EagerIslandRegistryTest extends TestCase
{
    public function testReturnsAllUrlsOnFirstTake(): void
    {
        $registry = new EagerIslandRegistry();

        $this->assertSame(
            ['vue.js', 'pinia.js'],
            $registry->take(['vue.js', 'pinia.js'])
        );
    }

    public function testSkipsAlreadyEmittedUrlsAcrossCalls(): void
    {
        $registry = new EagerIslandRegistry();
        $registry->take(['vue.js', 'pinia.js', 'customer-data.js']);

        // Only the chunk not seen before comes back.
        $this->assertSame(
            ['WishlistCount.js'],
            $registry->take(['vue.js', 'pinia.js', 'WishlistCount.js'])
        );
    }

    public function testDeduplicatesWithinASingleTake(): void
    {
        $registry = new EagerIslandRegistry();

        $this->assertSame(
            ['vue.js'],
            $registry->take(['vue.js', 'vue.js'])
        );
    }

    public function testEmptyWhenAllSeen(): void
    {
        $registry = new EagerIslandRegistry();
        $registry->take(['vue.js']);

        $this->assertSame([], $registry->take(['vue.js']));
    }
}
