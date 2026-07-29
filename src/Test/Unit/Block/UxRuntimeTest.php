<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\Block\UxRuntime;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use PHPUnit\Framework\TestCase;

/**
 * The UX global is emitted through SecureHtmlRenderer so it carries a CSP nonce
 * and survives the strict script-src enforced on checkout / customer-account
 * pages. Needs Magento framework view types, so it runs in a Magento root.
 */
class UxRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SecureHtmlRenderer::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheGlobalThroughTheSecureRenderer(): void
    {
        $html = $this->render(
            true,
            true,
            'window.__MAGE_OBSIDIAN_UX__ = {"optimistic":true,"summaryCountsQty":true};'
        );

        $this->assertStringContainsString('nonce="abc"', $html);
    }

    public function testCarriesBothFlagsVerbatim(): void
    {
        $html = $this->render(
            false,
            false,
            'window.__MAGE_OBSIDIAN_UX__ = {"optimistic":false,"summaryCountsQty":false};'
        );

        $this->assertStringContainsString('"optimistic":false', $html);
        $this->assertStringContainsString('"summaryCountsQty":false', $html);
    }

    private function render(bool $optimistic, bool $countsQty, string $expectedScript): string
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isOptimisticUiEnabled')->willReturn($optimistic);
        $configProvider->method('doesCartSummaryCountQty')->willReturn($countsQty);

        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->expects($this->once())
            ->method('renderTag')
            ->with('script', [], $expectedScript, false)
            ->willReturn('<script nonce="abc">' . $expectedScript . '</script>');

        $block = new UxRuntime($this->createMock(Context::class), $configProvider, $secureRenderer);

        return (new \ReflectionMethod($block, '_toHtml'))->invoke($block);
    }
}
