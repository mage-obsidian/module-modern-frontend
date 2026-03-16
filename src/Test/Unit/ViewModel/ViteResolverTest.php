<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\ViewModel;

use InvalidArgumentException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Asset\Repository;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Mock-based unit test for ViteResolver (asset/URL resolution + Vue mount HTML).
 * Requires the Magento autoloader (mocked framework types), so it runs inside a
 * Magento root rather than the standalone bootstrap. The prop encoding itself is
 * covered separately by {@see \MageObsidian\ModernFrontend\Test\Unit\Service\Vue\PropsEncoderTest}.
 */
class ViteResolverTest extends TestCase
{
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

        $random = $this->createMock(Random::class);
        $random->method('getUniqueHash')->willReturnCallback(static fn(string $prefix): string => $prefix . 'XYZ');

        $locale = $this->createMock(LocaleResolver::class);
        $locale->method('getLocale')->willReturn('en_US');

        return new ViteResolver($repository, $request, $configProvider, $random, $locale);
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

    public function testRenderVueComponentEmitsMountScript(): void
    {
        $html = $this->buildResolver()->renderVueComponent('Vendor::Card', ['label' => 'Hi']);

        $this->assertStringContainsString('<div id="vue-component-XYZ">', $html);
        $this->assertStringContainsString('createApp(Component, {"label":"Hi"})', $html);
        $this->assertStringContainsString(".use(obsidianI18n).mount('#vue-component-XYZ')", $html);
        $this->assertStringContainsString("from '/static/vite_generated/Vendor/components/Card.js'", $html);
        $this->assertStringContainsString("from '/static/vite_generated/lib/vue.js'", $html);
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
