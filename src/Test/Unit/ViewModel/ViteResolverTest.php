<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\ViewModel;

use InvalidArgumentException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Framework\View\Asset\Repository;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Service\EagerIslandRegistry;
use MageObsidian\ModernFrontend\Service\IslandManifest;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Mock-based unit test for ViteResolver (asset/URL resolution + island markup).
 * Requires the Magento autoloader (mocked framework types), so it runs inside a
 * Magento root rather than the standalone bootstrap. The prop encoding itself is
 * covered separately by {@see \MageObsidian\ModernFrontend\Test\Unit\Service\Vue\PropsEncoderTest}.
 */
class ViteResolverTest extends TestCase
{
    private EagerIslandRegistry $eagerIslandRegistry;

    /** Drives the IslandManifest mock used by buildResolver(). */
    private array $preloadFiles = [];

    protected function setUp(): void
    {
        $this->eagerIslandRegistry = new EagerIslandRegistry();
        $this->preloadFiles = [];
    }

    private function buildResolver(string $viteGeneratedPath = 'vite_generated'): ViteResolver
    {
        $repository = $this->createMock(Repository::class);
        // Echo the resolved fileId so path composition is observable.
        $repository->method('getUrlWithParams')
            ->willReturnCallback(static fn(string $fileId, array $params): string => '/static/' . $fileId);

        $request = $this->createMock(RequestInterface::class);
        $request->method('isSecure')->willReturn(false);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getViteGeneratedPath')->willReturn($viteGeneratedPath);

        $locale = $this->createMock(LocaleResolver::class);
        $locale->method('getLocale')->willReturn('en_US');

        $islandManifest = $this->createMock(IslandManifest::class);
        $islandManifest->method('getPreloadFiles')->willReturnCallback(fn(): array => $this->preloadFiles);

        return new ViteResolver(
            $repository,
            $request,
            $configProvider,
            $locale,
            $this->eagerIslandRegistry,
            $islandManifest
        );
    }

    public function testGetViteFileUrlAppendsJsExtensionAndPrefixesGeneratedPath(): void
    {
        $this->assertSame(
            '/static/vite_generated/Vendor/components/Card.js',
            $this->buildResolver()->getViteFileUrl('Vendor/components/Card')
        );
    }

    public function testGetViteFileUrlKeepsExistingExtension(): void
    {
        $this->assertSame(
            '/static/vite_generated/lib/styles.css',
            $this->buildResolver()->getViteFileUrl('lib/styles.css')
        );
    }

    public function testGetViteFileUrlThrowsWhenGeneratedPathMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->buildResolver('')->getViteFileUrl('anything');
    }

    public function testGetViteFileUrlStripsWhitespaceLeakedByTheDeployedVersion(): void
    {
        // A trailing newline in deployed_version.txt lands inside the URL; it must
        // not survive (it would make the JSON importmap consuming this URL invalid).
        $repository = $this->createMock(Repository::class);
        $repository->method('getUrlWithParams')
            ->willReturn("/static/version123\n/vite_generated/lib/vue.js");

        $request = $this->createMock(RequestInterface::class);
        $request->method('isSecure')->willReturn(false);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getViteGeneratedPath')->willReturn('vite_generated');

        $locale = $this->createMock(LocaleResolver::class);
        $locale->method('getLocale')->willReturn('en_US');

        $url = (new ViteResolver(
            $repository,
            $request,
            $configProvider,
            $locale,
            $this->eagerIslandRegistry,
            $this->createMock(IslandManifest::class)
        ))->getViteFileUrl('lib/vue');

        $this->assertStringNotContainsString("\n", $url);
        $this->assertSame('/static/version123/vite_generated/lib/vue.js', $url);
    }

    public function testResolvePathByNameSplitsVendorAndPath(): void
    {
        $this->assertSame(
            '/static/vite_generated/Vendor/Path.js',
            $this->buildResolver()->resolvePathByName('Vendor::Path')
        );
    }

    public function testResolvePathByNameFallsBackToThemeVendorAndDefaultStart(): void
    {
        // No "Vendor::" prefix → THEME_FILES_PATH vendor; defaultStart prepended.
        $this->assertSame(
            '/static/vite_generated/Theme/components/Card.js',
            $this->buildResolver()->resolveComponentPath('Card')
        );
    }

    public function testResolvePathByNameRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->buildResolver()->resolvePathByName('');
    }

    public function testRenderVueComponentEmitsLazyIslandMarker(): void
    {
        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', ['label' => 'Hi']);

        $this->assertStringContainsString('data-mage-island', $html);
        $this->assertStringContainsString(
            'data-component="/static/vite_generated/Vendor/components/Card.js"',
            $html
        );
        $this->assertStringContainsString('data-props="{&quot;label&quot;:&quot;Hi&quot;}"', $html);
        // Lazy ("visible") is the default strategy.
        $this->assertStringContainsString('data-strategy="visible"', $html);

        // The per-island inline mount script is gone — a single page bootstrap mounts it.
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('createApp', $html);
    }

    public function testRenderVueComponentEagerStrategyWhenRequested(): void
    {
        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', [], true);

        $this->assertStringContainsString('data-strategy="eager"', $html);
    }

    public function testRenderVueComponentEmitsModulePreloadForEagerIslandBeforeTheMarker(): void
    {
        // The manifest closure for this island (component chunk + shared deps).
        $this->preloadFiles = ['Vendor/components/Card.js', 'lib/pinia.js'];

        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', [], true);

        $this->assertStringContainsString(
            '<link rel="modulepreload" href="/static/vite_generated/Vendor/components/Card.js"/>',
            $html
        );
        $this->assertStringContainsString(
            '<link rel="modulepreload" href="/static/vite_generated/lib/pinia.js"/>',
            $html
        );
        // Hints must precede the marker that the bootstrap dynamically imports.
        $this->assertLessThan(
            strpos($html, 'data-mage-island'),
            strpos($html, '<link rel="modulepreload"')
        );
    }

    public function testRenderVueComponentVisibleIslandEmitsNoPreload(): void
    {
        $this->preloadFiles = ['Vendor/components/Card.js'];

        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', []);

        $this->assertStringNotContainsString('<link', $html);
    }

    public function testEagerPreloadIsDeduplicatedAcrossIslandsInTheSameRequest(): void
    {
        // Two eager islands sharing a chunk (pinia) — emitted once across both.
        $resolver = $this->buildResolver();

        $this->preloadFiles = ['Vendor/components/Card.js', 'lib/pinia.js'];
        $first = $resolver->renderVueComponent('Vendor::Card', [], true);

        $this->preloadFiles = ['Vendor/components/Other.js', 'lib/pinia.js'];
        $second = $resolver->renderVueComponent('Vendor::Other', [], true);

        $combined = $first . $second;
        $this->assertSame(
            1,
            substr_count($combined, '/static/vite_generated/lib/pinia.js'),
            'shared dependency chunk must be hinted exactly once per request'
        );
        $this->assertStringContainsString('Vendor/components/Other.js', $second);
    }

    public function testGetIslandsRuntimeUrlResolvesTheBootstrapAsset(): void
    {
        $this->assertSame(
            '/static/vite_generated/MageObsidian_ModernFrontend/js/islands.js',
            $this->buildResolver()->getIslandsRuntimeUrl()
        );
    }

    public function testGetI18nRuntimeConfigExposesLocaleAndDictionaryUrl(): void
    {
        $config = $this->buildResolver()->getI18nRuntimeConfig();

        $this->assertSame('en_US', $config['locale']);
        $this->assertSame('/static/js-translation.json', $config['dictionaryUrl']);
    }

    public function testGetHeroIconInlinesSvgWithNamespaceAndUseHref(): void
    {
        $svg = $this->buildResolver()->getHeroIcon('check', 'outline', '20');

        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('width="20" height="20"', $svg);
        $this->assertStringContainsString(
            '/static/MageObsidian_ModernFrontend::assets/@heroicons/20/outline/check.svg#icon',
            $svg
        );
    }
}
