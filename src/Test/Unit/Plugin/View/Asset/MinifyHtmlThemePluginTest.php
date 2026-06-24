<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\View\Asset;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Plugin\View\Asset\MinifyHtmlThemePlugin;
use Magento\Framework\View\Asset\ConfigInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Mocks Magento framework types, so it runs in a Magento root (see phpunit.xml)
 * and is excluded from the standalone CI suite.
 */
class MinifyHtmlThemePluginTest extends TestCase
{
    public function testFlagAlreadyOffStaysOffWithoutResolvingTheTheme(): void
    {
        $design = $this->createMock(DesignInterface::class);
        $design->expects($this->never())->method('getDesignTheme');
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->never())->method('isThemeEnabled');

        $plugin = $this->buildPlugin($design, $configManager);

        $this->assertFalse($plugin->afterIsMinifyHtml($this->createMock(ConfigInterface::class), false));
    }

    public function testObsidianThemeDisablesMinification(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('MageObsidian/default')->willReturn(true);

        $plugin = $this->buildPlugin($this->designReturning('MageObsidian/default'), $configManager);

        $this->assertFalse($plugin->afterIsMinifyHtml($this->createMock(ConfigInterface::class), true));
    }

    public function testLegacyThemeKeepsMinification(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('Magento/luma')->willReturn(false);

        $plugin = $this->buildPlugin($this->designReturning('Magento/luma'), $configManager);

        $this->assertTrue($plugin->afterIsMinifyHtml($this->createMock(ConfigInterface::class), true));
    }

    public function testEmptyThemeCodeKeepsNativeBehaviour(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->never())->method('isThemeEnabled');

        $plugin = $this->buildPlugin($this->designReturning(''), $configManager);

        $this->assertTrue($plugin->afterIsMinifyHtml($this->createMock(ConfigInterface::class), true));
    }

    public function testConfigFailureDegradesToNativeBehaviourAndLogs(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->willThrowException(new RuntimeException('contract missing'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $plugin = $this->buildPlugin($this->designReturning('MageObsidian/default'), $configManager, $logger);

        $this->assertTrue($plugin->afterIsMinifyHtml($this->createMock(ConfigInterface::class), true));
    }

    private function buildPlugin(
        DesignInterface $design,
        ConfigManagerInterface $configManager,
        ?LoggerInterface $logger = null
    ): MinifyHtmlThemePlugin {
        return new MinifyHtmlThemePlugin(
            $design,
            $configManager,
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    private function designReturning(string $themeCode): DesignInterface
    {
        $theme = $this->createMock(ThemeInterface::class);
        $theme->method('getCode')->willReturn($themeCode);
        $design = $this->createMock(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($theme);

        return $design;
    }
}
