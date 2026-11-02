<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Csp;

use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Model\Mode\Data\ModeConfigured;
use Magento\Csp\Model\Mode\Data\ModeConfiguredFactory;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Plugin\Csp\EnforceRecommendedPolicy;
use MageObsidian\ModernFrontend\Service\Csp\RecommendedPolicy;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use PHPUnit\Framework\TestCase;

class EnforceRecommendedPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(ModeConfiguredFactory::class)) {
            $this->markTestSkipped('Magento_Csp is not available in this runtime.');
        }
    }

    public function testEnforcesAStorefrontTheStoreLeftInReportOnly(): void
    {
        $result = $this->configured(new ModeConfigured(true, 'https://report.test/csp'));

        $this->assertFalse($result->isReportOnly());
        $this->assertSame('https://report.test/csp', $result->getReportUri());
    }

    public function testAnEnforcingModeIsReturnedAsItWas(): void
    {
        $enforcing = new ModeConfigured(false, null);

        $this->assertSame($enforcing, $this->configured($enforcing));
    }

    public function testStaysInReportOnlyWhileTheDevServerIsActive(): void
    {
        $reportOnly = new ModeConfigured(true, null);

        $this->assertSame($reportOnly, $this->configured($reportOnly, hmr: true));
    }

    public function testLeavesTheModeAloneWhenAModuleTurnedThePolicyOff(): void
    {
        $reportOnly = new ModeConfigured(true, null);

        $this->assertSame($reportOnly, $this->configured($reportOnly, enabled: false));
    }

    public function testLeavesTheModeAloneOutsideAnObsidianTheme(): void
    {
        $reportOnly = new ModeConfigured(true, null);

        $this->assertSame($reportOnly, $this->configured($reportOnly, obsidianTheme: false));
    }

    private function configured(
        ModeConfigured $mode,
        bool $enabled = true,
        bool $hmr = false,
        bool $obsidianTheme = true
    ): ModeConfigured {
        $configProvider = $this->createStub(ConfigProvider::class);
        $configProvider->method('isHmrEnabled')->willReturn($hmr);
        $detector = $this->createStub(ObsidianThemeDetector::class);
        $detector->method('isActive')->willReturn($obsidianTheme);
        $factory = $this->createStub(ModeConfiguredFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data): ModeConfigured => new ModeConfigured($data['reportOnly'], $data['reportUri'])
        );

        return (new EnforceRecommendedPolicy(new RecommendedPolicy($enabled), $detector, $configProvider, $factory))
            ->afterGetConfigured($this->createStub(ModeConfigManagerInterface::class), $mode);
    }
}
