<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\Block\SectionDataRuntime;
use MageObsidian\ModernFrontend\ViewModel\SectionDataConfig;
use PHPUnit\Framework\TestCase;

/**
 * The section-store config global is emitted through SecureHtmlRenderer so it
 * carries a CSP nonce and survives the strict script-src enforced on checkout /
 * customer-account pages (a raw <script> is blocked there). Needs Magento
 * framework view types, so it runs in a Magento root (see phpunit.xml).
 */
class SectionDataRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SecureHtmlRenderer::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheGlobalThroughTheSecureRenderer(): void
    {
        $config = $this->createMock(SectionDataConfig::class);
        $config->method('getConfig')->willReturn(['lifetime' => 3600, 'expirable' => ['cart']]);

        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->expects($this->once())
            ->method('renderTag')
            ->with(
                'script',
                [],
                'window.__MAGE_OBSIDIAN_SECTIONS__ = {"lifetime":3600,"expirable":["cart"]};',
                false
            )
            ->willReturn('<script nonce="abc">window.__MAGE_OBSIDIAN_SECTIONS__ = {};</script>');

        $block = new SectionDataRuntime($this->createMock(Context::class), $config, $secureRenderer);

        $method = new \ReflectionMethod($block, '_toHtml');
        $html = $method->invoke($block);

        $this->assertStringContainsString('nonce="abc"', $html);
    }
}
