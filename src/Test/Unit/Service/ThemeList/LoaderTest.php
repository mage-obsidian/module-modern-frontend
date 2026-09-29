<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\ThemeList;

use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Config\Dom\UrnResolver;
use Magento\Framework\Config\Theme as ThemeConfig;
use Magento\Framework\Config\ThemeFactory;
use Magento\Framework\Config\ValidationStateInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\View\Design\Theme\ThemePackage;
use Magento\Framework\View\Design\Theme\ThemePackageList;
use Magento\Framework\Xml\Parser;
use Magento\Framework\Xml\ParserFactory;
use MageObsidian\ModernFrontend\Service\ThemeList\Loader;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LoaderTest extends TestCase
{
    private const string COMPATIBLE = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:MageObsidian_ModernFrontend:etc/xsd/mage_obsidian_theme_compatibility.xsd"><features><compatibility>true</compatibility></features></config>';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/obsidian-theme-loader-' . uniqid();
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testReadsChildAndRootThemesWithTheShapeTheContractExpects(): void
    {
        $base = $this->theme('base', null, self::COMPATIBLE);
        $child = $this->theme('child', 'Acme/base', self::COMPATIBLE);

        $result = $this->loader([
            'frontend/Acme/base' => new ThemePackage('frontend/Acme/base', $base),
            'frontend/Acme/child' => new ThemePackage('frontend/Acme/child', $child),
        ])->load();

        $this->assertSame(
            [
                'code' => 'Acme/child',
                'parent_code' => 'Acme/base',
                'data' => ['features' => ['compatibility' => 'true']],
                'path' => $child,
            ],
            $result['Acme/child']
        );
        $this->assertNull($result['Acme/base']['parent_code']);
        $this->assertSame($base, $result['Acme/base']['path']);
    }

    public function testLeavesOutAdminhtmlThemes(): void
    {
        $admin = $this->theme('admin', null, self::COMPATIBLE);

        $result = $this->loader([
            'adminhtml/Acme/admin' => new ThemePackage('adminhtml/Acme/admin', $admin),
        ])->load();

        $this->assertSame([], $result);
    }

    public function testLeavesOutThemesWithoutACompatibilityDescriptor(): void
    {
        $plain = $this->theme('plain', null, null);

        $result = $this->loader([
            'frontend/Acme/plain' => new ThemePackage('frontend/Acme/plain', $plain),
        ])->load();

        $this->assertSame([], $result);
    }

    public function testAnUnreadableThemeXmlFailsNamingTheTheme(): void
    {
        $broken = $this->theme('broken', null, self::COMPATIBLE);
        file_put_contents($broken . '/theme.xml', '<theme><title>unterminated');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Acme/broken');

        $this->loader([
            'frontend/Acme/broken' => new ThemePackage('frontend/Acme/broken', $broken),
        ])->load();
    }

    private function theme(string $name, ?string $parent, ?string $compatibility): string
    {
        $dir = $this->root . '/' . $name;
        mkdir($dir . '/etc', 0777, true);
        $parentNode = $parent === null ? '' : '<parent>' . $parent . '</parent>';
        file_put_contents($dir . '/theme.xml', '<theme><title>' . $name . '</title>' . $parentNode . '</theme>');
        if ($compatibility !== null) {
            file_put_contents($dir . '/etc/mage_obsidian_compatibility.xml', $compatibility);
        }

        return $dir;
    }

    private function loader(array $packages): Loader
    {
        $registry = $this->createStub(ComponentRegistrarInterface::class);
        $registry->method('getPath')->willReturn(dirname(__DIR__, 4));

        $validationState = $this->createStub(ValidationStateInterface::class);
        $validationState->method('isValidationRequired')->willReturn(false);

        $packageList = $this->createStub(ThemePackageList::class);
        $packageList->method('getThemes')->willReturn($packages);

        $themeFactory = $this->createStub(ThemeFactory::class);
        $themeFactory->method('create')->willReturnCallback(
            fn (array $arguments): ThemeConfig => new ThemeConfig(new UrnResolver(), $arguments['configContent'])
        );

        return new Loader(
            $registry,
            $this->createStub(ModuleList::class),
            new File(),
            new Parser(),
            $validationState,
            $this->createStub(ParserFactory::class),
            $this->createStub(LoggerInterface::class),
            $packageList,
            $themeFactory
        );
    }
}
