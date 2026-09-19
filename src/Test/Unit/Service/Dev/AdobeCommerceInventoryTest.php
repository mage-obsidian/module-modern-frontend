<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Dev;

use MageObsidian\ModernFrontend\Service\Dev\AdobeCommerceInventory;
use PHPUnit\Framework\TestCase;

final class AdobeCommerceInventoryTest extends TestCase
{
    private AdobeCommerceInventory $inventory;

    protected function setUp(): void
    {
        $this->inventory = new AdobeCommerceInventory();
    }

    public function testDetectsAdobeCommerceByMarkerModule(): void
    {
        self::assertTrue($this->inventory->isAdobeCommerce(['Magento_Catalog', 'Magento_Enterprise']));
    }

    public function testOpenSourceIsNotAdobeCommerce(): void
    {
        self::assertFalse($this->inventory->isAdobeCommerce(['Magento_Catalog', 'Magento_Checkout']));
    }

    public function testInventoryListsOnlyActiveFamilies(): void
    {
        $result = $this->inventory->inventory(
            ['Magento_Enterprise', 'Magento_GiftCard', 'Magento_GiftCardAccount']
        );

        self::assertCount(1, $result);
        self::assertSame('Gift Card', $result[0]['family']);
        self::assertSame(['Magento_GiftCard', 'Magento_GiftCardAccount'], $result[0]['modules']);
        self::assertFalse($result[0]['covered']);
    }

    public function testFamilyIsCoveredWhenItsObsidianModuleIsInstalled(): void
    {
        $result = $this->inventory->inventory(
            ['Magento_Enterprise', 'Magento_Reward', 'MageObsidianCommerce_Reward']
        );

        self::assertCount(1, $result);
        self::assertTrue($result[0]['covered']);
        self::assertSame('MageObsidianCommerce_Reward', $result[0]['obsidian']);
    }

    public function testPartiallyActiveFamilyReportsOnlyTheActiveModules(): void
    {
        $result = $this->inventory->inventory(
            ['Magento_Enterprise', 'Magento_GiftCard']
        );

        self::assertSame(['Magento_GiftCard'], $result[0]['modules']);
    }

    public function testInventoryIsEmptyOnOpenSource(): void
    {
        self::assertSame([], $this->inventory->inventory(['Magento_Catalog']));
    }

    public function testFamiliesAreReturnedInDeclarationOrder(): void
    {
        $result = $this->inventory->inventory(
            ['Magento_Enterprise', 'Magento_Reward', 'Magento_GiftCard']
        );

        self::assertSame(['Gift Card', 'Reward Points'], array_column($result, 'family'));
    }

    public function testInvitationFamilyIsReportedUncovered(): void
    {
        $result = $this->inventory->inventory(
            ['Magento_Enterprise', 'Magento_Invitation']
        );

        self::assertCount(1, $result);
        self::assertSame('Invitation', $result[0]['family']);
        self::assertSame(['Magento_Invitation'], $result[0]['modules']);
        self::assertFalse($result[0]['covered']);
        self::assertSame('MageObsidianCommerce_Invitation', $result[0]['obsidian']);
    }
}
