<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\ModernFrontend\Service\CriticalCssProvider;
use MageObsidian\ModernFrontend\ViewModel\CriticalCss;
use PHPUnit\Framework\TestCase;

/**
 * Mocks Magento framework types, so it runs in a Magento root (see phpunit.xml)
 * and is excluded from the standalone CI suite.
 */
class CriticalCssTest extends TestCase
{
    private const VCLOAK = '[v-cloak]{display:none!important}';

    public function testInlinesCriticalForTheMostSpecificHandle(): void
    {
        $provider = $this->createMock(CriticalCssProvider::class);
        $provider->method('getCriticalCss')->willReturnMap([
            ['cms_index_index', '.hero{color:#000}'],
            ['default', '.base{}'],
        ]);

        $viewModel = new CriticalCss(
            $provider,
            $this->layoutWithHandles(['default', 'cms_index_index']),
            $this->scopeConfig(true)
        );

        $this->assertSame('.hero{color:#000}', $viewModel->getCriticalCssData());
    }

    public function testFallsBackToLessSpecificHandle(): void
    {
        $provider = $this->createMock(CriticalCssProvider::class);
        $provider->method('getCriticalCss')->willReturnMap([
            ['catalog_category_view', ''],
            ['default', '.base{}'],
        ]);

        $viewModel = new CriticalCss(
            $provider,
            $this->layoutWithHandles(['default', 'catalog_category_view']),
            $this->scopeConfig(true)
        );

        $this->assertSame('.base{}', $viewModel->getCriticalCssData());
    }

    public function testReturnsVcloakStubWhenNoCriticalExists(): void
    {
        $provider = $this->createMock(CriticalCssProvider::class);
        $provider->method('getCriticalCss')->willReturn('');

        $viewModel = new CriticalCss(
            $provider,
            $this->layoutWithHandles(['default']),
            $this->scopeConfig(true)
        );

        $this->assertSame(self::VCLOAK, $viewModel->getCriticalCssData());
    }

    public function testHasCriticalIsTrueWhenEnabledAndPresent(): void
    {
        $provider = $this->createMock(CriticalCssProvider::class);
        $provider->method('getCriticalCss')->willReturn('.hero{}');

        $viewModel = new CriticalCss(
            $provider,
            $this->layoutWithHandles(['default']),
            $this->scopeConfig(true)
        );

        $this->assertTrue($viewModel->hasCriticalCss());
    }

    public function testHasCriticalShortCircuitsWhenFlagOff(): void
    {
        $provider = $this->createMock(CriticalCssProvider::class);
        $provider->expects($this->never())->method('getCriticalCss');
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->never())->method('getUpdate');

        $viewModel = new CriticalCss($provider, $layout, $this->scopeConfig(false));

        $this->assertFalse($viewModel->hasCriticalCss());
    }

    public function testHasCriticalIsFalseWhenEnabledButEmpty(): void
    {
        $provider = $this->createMock(CriticalCssProvider::class);
        $provider->method('getCriticalCss')->willReturn('');

        $viewModel = new CriticalCss(
            $provider,
            $this->layoutWithHandles(['default']),
            $this->scopeConfig(true)
        );

        $this->assertFalse($viewModel->hasCriticalCss());
    }

    /**
     * @param string[] $handles
     */
    private function layoutWithHandles(array $handles): LayoutInterface
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->method('getHandles')->willReturn($handles);
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getUpdate')->willReturn($processor);

        return $layout;
    }

    private function scopeConfig(bool $enabled): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn($enabled);

        return $scopeConfig;
    }
}
