<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\Block\I18nRuntime;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\TestCase;

/**
 * The i18n config global is emitted through SecureHtmlRenderer so it carries a
 * CSP nonce and survives the strict script-src enforced on checkout /
 * customer-account pages (a raw <script> is blocked there). Needs Magento
 * framework view types, so it runs in a Magento root (see phpunit.xml).
 */
class I18nRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SecureHtmlRenderer::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheGlobalThroughTheSecureRenderer(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->method('getI18nRuntimeConfig')
            ->willReturn(['locale' => 'en_US', 'dictionaryUrl' => 'https://shop.test/js-translation.json']);

        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->expects($this->once())
            ->method('renderTag')
            ->with(
                'script',
                [],
                'window.__MAGE_OBSIDIAN_I18N__ = '
                . '{"locale":"en_US","dictionaryUrl":"https:\/\/shop.test\/js-translation.json"};',
                false
            )
            ->willReturn('<script nonce="abc">window.__MAGE_OBSIDIAN_I18N__ = {};</script>');

        $block = new I18nRuntime($this->createMock(Context::class), $resolver, $secureRenderer);

        $method = new \ReflectionMethod($block, '_toHtml');
        $html = $method->invoke($block);

        $this->assertStringContainsString('nonce="abc"', $html);
    }
}
