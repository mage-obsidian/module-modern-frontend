<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Theme;

use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ObsidianThemeDetectorTest extends TestCase
{
    public function testEnabledThemeIsActive(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('MageObsidian/default')->willReturn(true);

        $detector = $this->buildDetector($this->designReturning('MageObsidian/default'), $configManager);

        $this->assertTrue($detector->isActive());
    }

    public function testLegacyThemeIsNotActive(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('Magento/luma')->willReturn(false);

        $detector = $this->buildDetector($this->designReturning('Magento/luma'), $configManager);

        $this->assertFalse($detector->isActive());
    }

    public function testEmptyThemeCodeIsNotActiveAndSkipsTheContract(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->never())->method('isThemeEnabled');

        $detector = $this->buildDetector($this->designReturning(''), $configManager);

        $this->assertFalse($detector->isActive());
    }

    public function testFailureDegradesToNotActiveAndLogs(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->willThrowException(new RuntimeException('contract missing'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $detector = $this->buildDetector($this->designReturning('MageObsidian/default'), $configManager, $logger);

        $this->assertFalse($detector->isActive());
    }

    private function buildDetector(
        DesignInterface $design,
        ConfigManagerInterface $configManager,
        ?LoggerInterface $logger = null
    ): ObsidianThemeDetector {
        return new ObsidianThemeDetector(
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
