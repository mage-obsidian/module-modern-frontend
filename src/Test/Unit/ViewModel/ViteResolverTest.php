<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\ViewModel;

use InvalidArgumentException;
use Magento\Csp\Model\SubresourceIntegrity\SriEnabledActions;
use Magento\Framework\App\Request\Http;
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
        $this->integrity = [];
    }

    private const PAYMENT_ACTION = 'checkout_index_index';

    private array $integrity = [];

    private function buildResolver(
        string $viteGeneratedPath = 'vite_generated',
        bool $islandPreload = true,
        string $action = 'catalog_category_view'
    ): ViteResolver {
        $repository = $this->createMock(Repository::class);
        // Echo the resolved fileId so path composition is observable.
        $repository->method('getUrlWithParams')
            ->willReturnCallback(static fn(string $fileId, array $params): string => '/static/' . $fileId);

        $request = $this->createMock(Http::class);
        $request->method('isSecure')->willReturn(false);
        $request->method('getFullActionName')->willReturn($action);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getViteGeneratedPath')->willReturn($viteGeneratedPath);
        $configProvider->method('isIslandPreloadEnabled')->willReturn($islandPreload);

        $locale = $this->createMock(LocaleResolver::class);
        $locale->method('getLocale')->willReturn('en_US');

        $islandManifest = $this->createMock(IslandManifest::class);
        $islandManifest->method('getPreloadFiles')->willReturnCallback(fn(): array => $this->preloadFiles);
        $islandManifest->method('getIntegrityMap')->willReturnCallback(fn(): array => $this->integrity);

        return new ViteResolver(
            $repository,
            $request,
            $configProvider,
            $locale,
            $this->eagerIslandRegistry,
            $islandManifest,
            new SriEnabledActions(['checkout' => self::PAYMENT_ACTION])
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
            $this->createMock(IslandManifest::class),
            new SriEnabledActions()
        ))->getViteFileUrl('lib/vue');

        $this->assertStringNotContainsString("\n", $url);
        $this->assertSame('/static/version123/vite_generated/lib/vue.js', $url);
    }

    public function testAPaymentPageScriptCarriesItsHashAndCrossorigin(): void
    {
        $this->integrity = ['MageObsidian_Storefront/js/nav.js' => 'sha384-nav'];

        $this->assertSame(
            ['integrity' => 'sha384-nav', 'crossorigin' => 'anonymous'],
            $this->buildResolver(action: self::PAYMENT_ACTION)
                ->getIntegrityAttributes('MageObsidian_Storefront/js/nav')
        );
    }

    public function testAModuleQualifiedScriptNameResolvesToItsBuiltFile(): void
    {
        $this->integrity = ['MageObsidian_Storefront/js/nav.js' => 'sha384-nav'];

        $this->assertSame(
            ['integrity' => 'sha384-nav', 'crossorigin' => 'anonymous'],
            $this->buildResolver(action: self::PAYMENT_ACTION)
                ->getIntegrityAttributes('MageObsidian_Storefront::js/nav')
        );
    }

    public function testTheIslandsRuntimeCarriesItsHashOnAPaymentPage(): void
    {
        $this->integrity = ['MageObsidian_ModernFrontend/js/islands.js' => 'sha384-islands'];

        $this->assertSame(
            ['integrity' => 'sha384-islands', 'crossorigin' => 'anonymous'],
            $this->buildResolver(action: self::PAYMENT_ACTION)->getIslandsRuntimeIntegrityAttributes()
        );
    }

    public function testAPageThatIsNotAPaymentPageGetsNoIntegrity(): void
    {
        $this->integrity = ['MageObsidian_Storefront/js/nav.js' => 'sha384-nav'];

        $this->assertSame(
            [],
            $this->buildResolver()->getIntegrityAttributes('MageObsidian_Storefront/js/nav')
        );
    }

    public function testAPaymentPageScriptTheBuildNeverHashedGetsNoAttributes(): void
    {
        $this->assertSame(
            [],
            $this->buildResolver(action: self::PAYMENT_ACTION)->getIntegrityAttributes('Vendor/js/unknown')
        );
    }

    public function testEagerPreloadOnAPaymentPageCarriesIntegrity(): void
    {
        $this->preloadFiles = ['Vendor/components/Card.js', 'lib/pinia.js'];
        $this->integrity = ['Vendor/components/Card.js' => 'sha384-card', 'lib/pinia.js' => 'sha384-pinia'];

        $html = $this->buildResolver(action: self::PAYMENT_ACTION)
            ->renderVueComponent('Vendor::Card', [], true);

        $this->assertStringContainsString(
            '<link rel="modulepreload" href="/static/vite_generated/Vendor/components/Card.js" '
            . 'integrity="sha384-card" crossorigin="anonymous" fetchpriority="low"/>',
            $html
        );
        $this->assertStringContainsString(
            '<link rel="modulepreload" href="/static/vite_generated/lib/pinia.js" '
            . 'integrity="sha384-pinia" crossorigin="anonymous" fetchpriority="low"/>',
            $html
        );
    }

    public function testEagerPreloadOffAPaymentPageCarriesNoIntegrity(): void
    {
        $this->preloadFiles = ['Vendor/components/Card.js'];
        $this->integrity = ['Vendor/components/Card.js' => 'sha384-card'];

        $this->assertStringNotContainsString(
            'integrity',
            $this->buildResolver()->renderVueComponent('Vendor::Card', [], true)
        );
    }

    public function testTheImportMapIntegrityCoversEveryHashedChunkOnAPaymentPage(): void
    {
        $this->integrity = [
            'lib/vue.js' => 'sha384-vue',
            'assets/lazy-1.js' => 'sha384-lazy',
        ];

        $this->assertSame(
            [
                '/static/vite_generated/lib/vue.js' => 'sha384-vue',
                '/static/vite_generated/assets/lazy-1.js' => 'sha384-lazy',
            ],
            $this->buildResolver(action: self::PAYMENT_ACTION)->getImportMapIntegrity()
        );
    }

    public function testTheImportMapIntegrityIsEmptyOffAPaymentPage(): void
    {
        $this->integrity = ['lib/vue.js' => 'sha384-vue'];

        $this->assertSame([], $this->buildResolver()->getImportMapIntegrity());
    }

    public function testARequestWithoutAnActionNameIsNeverAPaymentPage(): void
    {
        $this->integrity = ['lib/vue.js' => 'sha384-vue'];
        $repository = $this->createStub(Repository::class);
        $repository->method('getUrlWithParams')->willReturn('/static/vite_generated/lib/vue.js');
        $configProvider = $this->createStub(ConfigProvider::class);
        $configProvider->method('getViteGeneratedPath')->willReturn('vite_generated');
        $islandManifest = $this->createStub(IslandManifest::class);
        $islandManifest->method('getIntegrityMap')->willReturn($this->integrity);

        $resolver = new ViteResolver(
            $repository,
            $this->createStub(RequestInterface::class),
            $configProvider,
            $this->createStub(LocaleResolver::class),
            $this->eagerIslandRegistry,
            $islandManifest,
            new SriEnabledActions([self::PAYMENT_ACTION])
        );

        $this->assertSame([], $resolver->getImportMapIntegrity());
    }

    public function testTheImportMapMapsVueAndCarriesIntegrityOnAPaymentPage(): void
    {
        $this->integrity = ['lib/vue.js' => 'sha384-vue', 'assets/lazy-1.js' => 'sha384-lazy'];

        $this->assertSame(
            [
                'imports' => ['vue' => '/static/vite_generated/lib/vue.js'],
                'integrity' => [
                    '/static/vite_generated/lib/vue.js' => 'sha384-vue',
                    '/static/vite_generated/assets/lazy-1.js' => 'sha384-lazy',
                ],
            ],
            $this->buildResolver(action: self::PAYMENT_ACTION)->getImportMap()
        );
    }

    public function testTheImportMapOffAPaymentPageHasNoIntegritySection(): void
    {
        $this->integrity = ['lib/vue.js' => 'sha384-vue'];

        $this->assertSame(
            ['imports' => ['vue' => '/static/vite_generated/lib/vue.js']],
            $this->buildResolver()->getImportMap()
        );
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

    public function testRenderVueComponentMarkerIsEmptyWithoutServerHtml(): void
    {
        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', [], true);

        $this->assertStringContainsString('data-strategy="eager"></div>', $html);
        $this->assertStringNotContainsString('data-hydrate', $html);
    }

    public function testRenderVueComponentEmitsServerHtmlInsideMarker(): void
    {
        $html = $this->buildResolver()->renderVueComponent(
            'Vendor::Card',
            [],
            true,
            '<span class="price">$49.00</span>',
            true
        );

        $this->assertStringContainsString(
            'data-strategy="eager" data-hydrate><span class="price">$49.00</span></div>',
            $html
        );
    }

    public function testServerHtmlIsAPlaceholderUnlessHydrationIsAskedFor(): void
    {
        // Markup that only approximates the component must not be hydrated: the
        // runtime clears it, exactly as it did before hydration existed.
        $html = $this->buildResolver()->renderVueComponent(
            'Vendor::Card',
            [],
            true,
            '<span class="skeleton">icon</span>'
        );

        $this->assertStringContainsString(
            'data-strategy="eager"><span class="skeleton">icon</span></div>',
            $html
        );
        $this->assertStringNotContainsString('data-hydrate', $html);
    }

    public function testHydrationIsNotClaimedForEmptyServerHtml(): void
    {
        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', [], true, '', true);

        $this->assertStringNotContainsString('data-hydrate', $html);
    }

    public function testServerHtmlDropsTheWhitespaceTemplateIndentationAdds(): void
    {
        $html = $this->buildResolver()->renderVueComponent(
            'Vendor::Card',
            [],
            true,
            "\n    <form>\n        <p>\$49.00</p>\n        <span>28</span>\n    </form>\n",
            true
        );

        $this->assertStringContainsString(
            'data-hydrate><form><p>$49.00</p><span>28</span></form></div>',
            $html
        );
    }

    public function testServerHtmlDropsIndentationLeftBehindByATwigBlockTag(): void
    {
        // Twig eats the newline after `{% endapply %}`, so this arrives as bare
        // spaces; Vue expects no node at all between the two.
        $html = $this->buildResolver()->renderVueComponent(
            'Vendor::Card',
            [],
            true,
            "<span>a</span><!---->        </div>"
        );

        $this->assertStringContainsString('<span>a</span><!----></div>', $html);
    }

    public function testServerHtmlKeepsTextInsideAnElementUntouched(): void
    {
        $html = $this->buildResolver()->renderVueComponent(
            'Vendor::Card',
            [],
            true,
            "<p>two   spaces</p>\n<p>and\na newline</p>"
        );

        $this->assertStringContainsString('<p>two   spaces</p><p>and' . "\n" . 'a newline</p>', $html);
    }

    public function testServerHtmlThatIsOnlyWhitespaceLeavesTheMarkerEmpty(): void
    {
        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', [], true, "\n   \n");

        $this->assertStringContainsString('data-strategy="eager"></div>', $html);
        $this->assertStringNotContainsString('data-hydrate', $html);
    }

    public function testRenderVueComponentEmitsModulePreloadForEagerIslandBeforeTheMarker(): void
    {
        // The manifest closure for this island (component chunk + shared deps).
        $this->preloadFiles = ['Vendor/components/Card.js', 'lib/pinia.js'];

        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', [], true);

        $this->assertStringContainsString(
            '<link rel="modulepreload" href="/static/vite_generated/Vendor/components/Card.js" fetchpriority="low"/>',
            $html
        );
        $this->assertStringContainsString(
            '<link rel="modulepreload" href="/static/vite_generated/lib/pinia.js" fetchpriority="low"/>',
            $html
        );
        // Hints must precede the marker that the bootstrap dynamically imports.
        $this->assertLessThan(
            strpos($html, 'data-mage-island'),
            strpos($html, '<link rel="modulepreload"')
        );
    }

    public function testEagerIslandEmitsNoPreloadWhenTheStoreHasNotAskedForIt(): void
    {
        $this->preloadFiles = ['Vendor/components/Card.js', 'lib/pinia.js'];

        $html = $this->buildResolver('vite_generated', false)->renderVueComponent('Vendor::Card', [], true);

        $this->assertStringNotContainsString('modulepreload', $html);
        $this->assertStringContainsString('data-strategy="eager"', $html);
    }

    public function testRenderVueComponentVisibleIslandEmitsNoPreload(): void
    {
        $this->preloadFiles = ['Vendor/components/Card.js'];

        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', []);

        $this->assertStringNotContainsString('<link', $html);
    }

    public function testEveryEagerPreloadHintCarriesLowFetchPriority(): void
    {
        $this->preloadFiles = ['Vendor/components/Card.js', 'lib/pinia.js', 'lib/vue.js'];

        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', [], true);

        $this->assertSame(
            substr_count($html, '<link rel="modulepreload"'),
            substr_count($html, 'fetchpriority="low"'),
            'island preload hints must never outrank the render-blocking stylesheet'
        );
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

    /**
     * An icon inside an island's server markup is hydrated against a component
     * that renders no whitespace, and the indentation alone makes Vue reject the
     * subtree — so the markup has to arrive on one line.
     */
    public function testGetHeroIconEmitsMarkupWithoutWhitespaceBetweenTags(): void
    {
        $svg = $this->buildResolver()->getHeroIcon('check', 'outline', '20', 'h-5 w-5');

        $this->assertSame($svg, trim($svg));
        $this->assertDoesNotMatchRegularExpression('/>\s+</', $svg);
        $this->assertStringContainsString('class="h-5 w-5"', $svg);
    }
}
