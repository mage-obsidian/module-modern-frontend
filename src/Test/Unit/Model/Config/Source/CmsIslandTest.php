<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Config\Source;

use MageObsidian\ModernFrontend\Model\Config\Source\CmsIsland;
use MageObsidian\ModernFrontend\Service\Cms\IslandRegistry;
use MageObsidian\ModernFrontend\Service\IslandManifest;
use PHPUnit\Framework\TestCase;

/**
 * Two lists that mean different things: what a module allows in content, and
 * what this theme actually built. Offering their intersection is what keeps an
 * author from picking a component whose chunk is a 404.
 */
class CmsIslandTest extends TestCase
{
    private function source(array $islands, array $built): CmsIsland
    {
        $manifest = $this->createMock(IslandManifest::class);
        $manifest->method('getBuiltComponents')->willReturn($built);

        return new CmsIsland(new IslandRegistry($islands), $manifest);
    }

    public function testOffersOnlyRegisteredComponentsThatWereBuilt(): void
    {
        $source = $this->source(
            [
                'carousel' => ['component' => 'Vendor_Module::catalog/ProductCarousel', 'label' => 'Carousel'],
                'banner' => ['component' => 'Vendor_Module::cms/Banner', 'label' => 'Banner'],
            ],
            ['Vendor_Module::catalog/ProductCarousel', 'Vendor_Module::checkout/PaymentStep']
        );

        $this->assertSame(
            [['value' => 'Vendor_Module::catalog/ProductCarousel', 'label' => 'Carousel']],
            $source->toOptionArray()
        );
    }

    public function testAnUnreadableManifestMeansUnknown_NotEmpty(): void
    {
        $source = $this->source(
            ['carousel' => ['component' => 'Vendor_Module::catalog/ProductCarousel', 'label' => 'Carousel']],
            []
        );

        $this->assertSame(
            [['value' => 'Vendor_Module::catalog/ProductCarousel', 'label' => 'Carousel']],
            $source->toOptionArray()
        );
    }

    public function testAnEmptyRegistryOffersNothingEvenWhenComponentsExist(): void
    {
        $this->assertSame([], $this->source([], ['Vendor_Module::catalog/ProductCarousel'])->toOptionArray());
    }
}
