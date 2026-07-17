<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Deploy\Package;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Plugin\Deploy\Package\PackageFilePlugin;
use Magento\Deploy\Package\Package;
use Magento\Deploy\Package\PackageFile;
use PHPUnit\Framework\TestCase;

class PackageFilePluginTest extends TestCase
{
    private function plugin(ConfigManagerInterface $configManager): PackageFilePlugin
    {
        return new PackageFilePlugin($configManager);
    }

    private function file(string $module, string $theme, string $sourcePath, string $area = 'frontend'): PackageFile
    {
        $file = $this->createMock(PackageFile::class);
        $file->method('getModule')->willReturn($module);
        $file->method('getTheme')->willReturn($theme);
        $file->method('getArea')->willReturn($area);
        $file->method('getSourcePath')->willReturn($sourcePath);
        return $file;
    }

    private function package(string $theme): Package
    {
        $package = $this->createMock(Package::class);
        $package->method('getTheme')->willReturn($theme);
        return $package;
    }

    /**
     * The defect: a MageObsidian module's ESM web asset reaching the legacy
     * pipeline. It must be excluded even when the package being deployed is a
     * legacy (disabled) theme such as Magento/blank.
     */
    public function testExcludesObsidianModuleEsmFromLegacyThemePackage(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isModuleEnabled')->with('MageObsidian_Catalog')->willReturn(true);

        $file = $this->file(
            'MageObsidian_Catalog',
            '',
            '/abs/module-catalog/src/view/frontend/web/js/gallery.js'
        );
        $package = $this->package('Magento/blank');

        $proceed = function () {
            $this->fail('proceed() must not be called for an excluded file');
        };

        $this->assertNull($this->plugin($configManager)->aroundSetPackage($file, $proceed, $package));
    }

    /**
     * A non-web asset of the same module (e.g. an image) is not Vite-only and
     * must keep flowing through the native pipeline.
     */
    public function testKeepsObsidianModuleNonWebAssetUnderLegacyTheme(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isModuleEnabled')->with('MageObsidian_Catalog')->willReturn(true);

        $file = $this->file(
            'MageObsidian_Catalog',
            '',
            '/abs/module-catalog/src/view/frontend/web/images/logo.svg'
        );
        $package = $this->package('Magento/blank');

        $called = false;
        $proceed = function () use (&$called) {
            $called = true;
            return 'kept';
        };

        $this->assertSame('kept', $this->plugin($configManager)->aroundSetPackage($file, $proceed, $package));
        $this->assertTrue($called);
    }

    /**
     * A legacy theme's own files (no MageObsidian module) deploy untouched.
     */
    public function testKeepsLegacyThemeOwnFiles(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('Magento/blank')->willReturn(false);

        $file = $this->file('', 'Magento/blank', '/abs/blank/web/css/styles.css');
        $package = $this->package('Magento/blank');

        $called = false;
        $proceed = function () use (&$called) {
            $called = true;
            return 'kept';
        };

        $this->assertSame('kept', $this->plugin($configManager)->aroundSetPackage($file, $proceed, $package));
        $this->assertTrue($called);
    }

    /**
     * A third-party (non-MageObsidian) module's assets are not ours to filter
     * out of a legacy theme deploy.
     */
    public function testKeepsThirdPartyModuleAssetUnderLegacyTheme(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isModuleEnabled')->with('Magento_Catalog')->willReturn(false);
        $configManager->method('isThemeEnabled')->with('Magento/blank')->willReturn(false);

        $file = $this->file('Magento_Catalog', '', '/abs/magento/Catalog/view/frontend/web/js/foo.js');
        $package = $this->package('Magento/blank');

        $called = false;
        $proceed = function () use (&$called) {
            $called = true;
            return 'kept';
        };

        $this->assertSame('kept', $this->plugin($configManager)->aroundSetPackage($file, $proceed, $package));
        $this->assertTrue($called);
    }

    /**
     * The reported bug: a non-MageObsidian module's runtime static asset
     * (svg/image/font, not imported by Vite) must still be materialized for an
     * enabled MageObsidian theme, exactly as it is for legacy themes.
     */
    public function testKeepsThirdPartyModuleStaticAssetUnderEnabledTheme(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isModuleEnabled')->with('Magento_Catalog')->willReturn(false);
        $configManager->method('isThemeEnabled')->with('MageObsidian/default')->willReturn(true);

        $file = $this->file(
            'Magento_Catalog',
            '',
            '/abs/magento/Catalog/view/frontend/web/svg/parts/default/foo.svg'
        );
        $package = $this->package('MageObsidian/default');

        $called = false;
        $proceed = function () use (&$called) {
            $called = true;
            return 'kept';
        };

        $this->assertSame('kept', $this->plugin($configManager)->aroundSetPackage($file, $proceed, $package));
        $this->assertTrue($called);
    }

    /**
     * Intentional: a third-party module's legacy JS has nowhere to be wired on
     * an enabled MageObsidian theme (no RequireJS pipeline), so it stays out.
     */
    public function testExcludesThirdPartyModuleJsUnderEnabledTheme(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isModuleEnabled')->with('Magento_Catalog')->willReturn(false);
        $configManager->method('isThemeEnabled')->with('MageObsidian/default')->willReturn(true);

        $file = $this->file('Magento_Catalog', '', '/abs/magento/Catalog/view/frontend/web/js/foo.js');
        $package = $this->package('MageObsidian/default');

        $proceed = function () {
            $this->fail('proceed() must not be called for an excluded file');
        };

        $this->assertNull($this->plugin($configManager)->aroundSetPackage($file, $proceed, $package));
    }

    /**
     * An enabled MageObsidian module's own static asset also deploys under an
     * enabled MageObsidian theme (documents the scenario from the bug report).
     */
    public function testKeepsObsidianModuleStaticAssetUnderEnabledTheme(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isModuleEnabled')->with('MageObsidian_Catalog')->willReturn(true);
        $configManager->method('isThemeEnabled')->with('MageObsidian/default')->willReturn(true);

        $file = $this->file(
            'MageObsidian_Catalog',
            '',
            '/abs/module-catalog/src/view/frontend/web/svg/icon.svg'
        );
        $package = $this->package('MageObsidian/default');

        $called = false;
        $proceed = function () use (&$called) {
            $called = true;
            return 'kept';
        };

        $this->assertSame('kept', $this->plugin($configManager)->aroundSetPackage($file, $proceed, $package));
        $this->assertTrue($called);
    }
}
