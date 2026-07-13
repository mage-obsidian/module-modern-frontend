<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Framework\Module;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Framework\Module\Manager;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\Output\ConfigInterface as OutputConfigInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use PHPUnit\Framework\TestCase;

/**
 * Covers the layout-file gating in isOutputEnabled(): the theme×module matrix
 * plus the <universal> opt-in. Mocks the Magento framework collaborators, so it
 * needs the framework autoloader (runs in a Magento root, not the standalone CI
 * suite).
 */
class ManagerTest extends TestCase
{
    private const MODULE = 'Vendor_Mod';
    private const THEME = 'Vendor/theme';

    public function testObsidianThemeKeepsContractModule(): void
    {
        $manager = $this->makeManager(themeEnabled: true, moduleInContract: true);
        $this->assertTrue($manager->isOutputEnabled(self::MODULE));
    }

    public function testObsidianThemeDropsNonContractModule(): void
    {
        $manager = $this->makeManager(themeEnabled: true, moduleInContract: false);
        $this->assertFalse($manager->isOutputEnabled(self::MODULE));
    }

    public function testLegacyThemeDropsContractModule(): void
    {
        $manager = $this->makeManager(themeEnabled: false, moduleInContract: true);
        $this->assertFalse($manager->isOutputEnabled(self::MODULE));
    }

    public function testLegacyThemeKeepsUniversalContractModule(): void
    {
        $manager = $this->makeManager(themeEnabled: false, moduleInContract: true, universal: true);
        $this->assertTrue($manager->isOutputEnabled(self::MODULE));
    }

    public function testLegacyThemeKeepsNativeModule(): void
    {
        $manager = $this->makeManager(themeEnabled: false, moduleInContract: false);
        $this->assertTrue($manager->isOutputEnabled(self::MODULE));
    }

    public function testModuleDisabledInMagentoIsAlwaysOff(): void
    {
        // parent::isOutputEnabled() false → short-circuit before any contract
        // lookup, regardless of theme.
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->willReturn(true);
        $configManager->expects($this->never())->method('isModuleEnabled');
        $configManager->expects($this->never())->method('isModuleUniversal');

        $manager = $this->buildManager($configManager, nativeHas: false);

        $this->assertFalse($manager->isOutputEnabled(self::MODULE));
    }

    private function makeManager(
        bool $themeEnabled,
        bool $moduleInContract = false,
        bool $universal = false
    ): Manager {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->willReturn($themeEnabled);
        $configManager->method('isModuleEnabled')->willReturn($moduleInContract);
        $configManager->method('isModuleUniversal')->willReturn($universal);

        return $this->buildManager($configManager);
    }

    private function buildManager(ConfigManagerInterface $configManager, bool $nativeHas = true): Manager
    {
        $outputConfig = $this->createMock(OutputConfigInterface::class);
        // isEnabled() here means "output disabled via config"; false keeps the
        // native result equal to moduleList->has().
        $outputConfig->method('isEnabled')->willReturn(false);

        $moduleList = $this->createMock(ModuleListInterface::class);
        $moduleList->method('has')->willReturn($nativeHas);

        $theme = $this->createMock(ThemeInterface::class);
        $theme->method('getCode')->willReturn(self::THEME);
        $design = $this->createMock(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($theme);

        return new Manager($outputConfig, $moduleList, $design, $configManager);
    }
}
