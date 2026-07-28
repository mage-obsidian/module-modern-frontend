<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Cms;

use MageObsidian\ModernFrontend\Service\Cms\IslandRegistry;
use PHPUnit\Framework\TestCase;

/**
 * The registry is fed by a merged di.xml array, so it is written by several
 * modules that never see each other's entries. Anything malformed has to be
 * dropped rather than reach the widget dropdown as a broken option.
 */
class IslandRegistryTest extends TestCase
{
    public function testNormalizesAnEntryAndIndexesItByComponent(): void
    {
        $registry = new IslandRegistry([
            'product_carousel' => [
                'component' => 'Vendor_Module::catalog/ProductCarousel',
                'label' => 'Product carousel',
                'description' => 'A row of products.',
                'props' => ['limit' => 4],
            ],
        ]);

        $this->assertSame(
            [
                'Vendor_Module::catalog/ProductCarousel' => [
                    'component' => 'Vendor_Module::catalog/ProductCarousel',
                    'label' => 'Product carousel',
                    'description' => 'A row of products.',
                    'props' => ['limit' => 4],
                ],
            ],
            $registry->getAll()
        );
    }

    public function testFallsBackToTheEntryKeyAsTheLabel(): void
    {
        $registry = new IslandRegistry([
            'product_carousel' => ['component' => 'Vendor_Module::catalog/ProductCarousel'],
        ]);

        $island = $registry->get('Vendor_Module::catalog/ProductCarousel');

        $this->assertSame('product_carousel', $island['label']);
        $this->assertSame('', $island['description']);
        $this->assertSame([], $island['props']);
    }

    /**
     * @dataProvider malformedEntries
     */
    public function testDropsAnEntryItCannotUse(mixed $entry): void
    {
        $this->assertSame([], (new IslandRegistry(['broken' => $entry]))->getAll());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedEntries(): array
    {
        return [
            'not an array' => ['Vendor_Module::catalog/ProductCarousel'],
            'no component' => [['label' => 'Product carousel']],
            'empty component' => [['component' => '']],
            'component is not a string' => [['component' => ['Vendor_Module::x']]],
        ];
    }

    public function testAnswersWhetherAComponentIsAllowed(): void
    {
        $registry = new IslandRegistry([
            'carousel' => ['component' => 'Vendor_Module::catalog/ProductCarousel'],
        ]);

        $this->assertTrue($registry->has('Vendor_Module::catalog/ProductCarousel'));
        $this->assertFalse($registry->has('Vendor_Module::checkout/PaymentStep'));
        $this->assertNull($registry->get('Vendor_Module::checkout/PaymentStep'));
    }

    public function testAnEmptyRegistryExposesNothing(): void
    {
        $registry = new IslandRegistry();

        $this->assertSame([], $registry->getAll());
        $this->assertFalse($registry->has('Vendor_Module::catalog/ProductCarousel'));
    }
}
