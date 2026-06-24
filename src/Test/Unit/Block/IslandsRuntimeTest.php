<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\Block\IslandsRuntime;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\TestCase;

/**
 * The bootstrap block only emits the page-level islands script; the eager
 * modulepreload hints are emitted inline by ViteResolver::renderVueComponent.
 * Needs Magento's block Context, so it runs in a Magento root (see phpunit.xml).
 */
class IslandsRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheBootstrapModuleScript(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->method('getIslandsRuntimeUrl')
            ->willReturn('/static/generated/MageObsidian_ModernFrontend/js/islands.js');

        $block = new IslandsRuntime($this->createMock(Context::class), $resolver);

        $method = new \ReflectionMethod($block, '_toHtml');
        $html = (string)$method->invoke($block);

        $this->assertSame(
            '<script type="module" src="/static/generated/MageObsidian_ModernFrontend/js/islands.js"></script>',
            $html
        );
    }
}
