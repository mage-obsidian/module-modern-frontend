<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Theme;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Design\Theme\FlyweightFactory;
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

    public function testAStorefrontResponseServedBeforeAnyThemeLoadedUsesTheStoreTheme(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('MageObsidian/default')->willReturn(true);

        $detector = $this->buildDetector(
            $this->designReturning('', '5'),
            $configManager,
            themes: $this->themesLoading('5', 'MageObsidian/default')
        );

        $this->assertTrue($detector->isActive());
    }

    public function testAStorefrontResponseServedBeforeAnyThemeLoadedOnALegacyStoreIsNotActive(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('Magento/luma')->willReturn(false);

        $detector = $this->buildDetector(
            $this->designReturning('', '3'),
            $configManager,
            themes: $this->themesLoading('3', 'Magento/luma')
        );

        $this->assertFalse($detector->isActive());
    }

    public function testALoadedThemeWinsOverTheStoreTheme(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('Magento/luma')->willReturn(false);
        $themes = $this->createMock(FlyweightFactory::class);
        $themes->expects($this->never())->method('create');

        $detector = $this->buildDetector($this->designReturning('Magento/luma', '5'), $configManager, themes: $themes);

        $this->assertFalse($detector->isActive());
    }

    public function testAStoreWithoutAConfiguredThemeIsNotActive(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->never())->method('isThemeEnabled');
        $themes = $this->createMock(FlyweightFactory::class);
        $themes->expects($this->never())->method('create');

        $detector = $this->buildDetector($this->designReturning(''), $configManager, themes: $themes);

        $this->assertFalse($detector->isActive());
    }

    public function testEmptyThemeCodeOutsideTheStorefrontIsNotActiveAndSkipsTheContract(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->never())->method('isThemeEnabled');
        $themes = $this->createMock(FlyweightFactory::class);
        $themes->expects($this->never())->method('create');

        $detector = $this->buildDetector(
            $this->designReturning('', '5'),
            $configManager,
            state: $this->stateIn(Area::AREA_ADMINHTML),
            themes: $themes
        );

        $this->assertFalse($detector->isActive());
    }

    public function testEmptyThemeCodeWithoutAnAreaIsNotActiveAndLogsNothing(): void
    {
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willThrowException(new LocalizedException(new Phrase('Area code is not set')));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $detector = $this->buildDetector(
            $this->designReturning('', '5'),
            $this->createMock(ConfigManagerInterface::class),
            $logger,
            $state
        );

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
        ?LoggerInterface $logger = null,
        ?State $state = null,
        ?FlyweightFactory $themes = null
    ): ObsidianThemeDetector {
        return new ObsidianThemeDetector(
            $design,
            $configManager,
            $logger ?? $this->createMock(LoggerInterface::class),
            $state ?? $this->stateIn(Area::AREA_FRONTEND),
            $themes ?? $this->createMock(FlyweightFactory::class)
        );
    }

    private function designReturning(string $themeCode, ?string $storeTheme = null): DesignInterface
    {
        $theme = $this->createMock(ThemeInterface::class);
        $theme->method('getCode')->willReturn($themeCode);
        $design = $this->createMock(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($theme);
        $design->method('getConfigurationDesignTheme')->with(Area::AREA_FRONTEND)->willReturn($storeTheme);

        return $design;
    }

    private function stateIn(string $area): State
    {
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn($area);

        return $state;
    }

    private function themesLoading(string $themeKey, string $themeCode): FlyweightFactory
    {
        $theme = $this->createMock(ThemeInterface::class);
        $theme->method('getCode')->willReturn($themeCode);
        $themes = $this->createMock(FlyweightFactory::class);
        $themes->expects($this->once())->method('create')->with($themeKey, Area::AREA_FRONTEND)->willReturn($theme);

        return $themes;
    }
}
