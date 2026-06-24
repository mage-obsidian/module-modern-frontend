<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\Block\IslandsRuntime;
use MageObsidian\ModernFrontend\Service\EagerIslandRegistry;
use MageObsidian\ModernFrontend\Service\IslandManifest;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the bootstrap block emits the eager islands' modulepreload hints
 * ahead of the bootstrap script. Needs Magento's block Context, so it runs in a
 * Magento root (see phpunit.xml), like the other block tests.
 */
class IslandsRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function buildBlock(EagerIslandRegistry $registry, IslandManifest $manifest): IslandsRuntime
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->method('getIslandsRuntimeUrl')
            ->willReturn('/static/generated/MageObsidian_ModernFrontend/js/islands.js');

        return new IslandsRuntime($this->createMock(Context::class), $resolver, $registry, $manifest);
    }

    private function render(IslandsRuntime $block): string
    {
        $method = new \ReflectionMethod($block, '_toHtml');

        return (string)$method->invoke($block);
    }

    public function testEmitsModulePreloadLinksBeforeTheBootstrapScript(): void
    {
        $registry = new EagerIslandRegistry();
        $registry->register('MageObsidian_Storefront/components/wishlist/WishlistCount.js');

        $manifest = $this->createMock(IslandManifest::class);
        $manifest->expects($this->once())
            ->method('getPreloadUrls')
            ->with(['MageObsidian_Storefront/components/wishlist/WishlistCount.js'])
            ->willReturn([
                '/static/generated/MageObsidian_Storefront/components/wishlist/WishlistCount.js',
                '/static/generated/lib/pinia.js',
            ]);

        $html = $this->render($this->buildBlock($registry, $manifest));

        $this->assertStringContainsString(
            '<link rel="modulepreload" href="/static/generated/lib/pinia.js"/>',
            $html
        );
        // Hints must precede the script that triggers the dynamic imports.
        $this->assertLessThan(
            strpos($html, '<script'),
            strpos($html, '<link rel="modulepreload"'),
            'modulepreload links must come before the bootstrap script'
        );
        $this->assertStringContainsString(
            '<script type="module" src="/static/generated/MageObsidian_ModernFrontend/js/islands.js"></script>',
            $html
        );
    }

    public function testEmitsOnlyTheScriptWhenThereAreNoPreloads(): void
    {
        $manifest = $this->createMock(IslandManifest::class);
        $manifest->method('getPreloadUrls')->willReturn([]);

        $html = $this->render($this->buildBlock(new EagerIslandRegistry(), $manifest));

        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringContainsString('<script type="module"', $html);
    }
}
