<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Csp;

use Magento\Csp\Api\Data\ModeConfiguredInterface;
use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Model\Mode\Data\ModeConfigured;
use Magento\Csp\Model\Mode\Data\ModeConfiguredFactory;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Plugin\Csp\ForceReportOnlyInHmr;
use PHPUnit\Framework\TestCase;

/**
 * Mocks Magento\Csp types, so it needs the framework autoloader (runs in a
 * Magento root, not the standalone CI suite).
 */
class ForceReportOnlyInHmrTest extends TestCase
{
    public function testForcesReportOnlyWhenHmrEnabledAndModeEnforcing(): void
    {
        $subject = $this->createMock(ModeConfigManagerInterface::class);

        $enforcing = $this->createMock(ModeConfiguredInterface::class);
        $enforcing->method('isReportOnly')->willReturn(false);
        $enforcing->method('getReportUri')->willReturn('https://report.test/csp');

        $relaxed = new ModeConfigured(true, 'https://report.test/csp');

        $factory = $this->createMock(ModeConfiguredFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(['reportOnly' => true, 'reportUri' => 'https://report.test/csp'])
            ->willReturn($relaxed);

        $result = $this->makePlugin(hmr: true, factory: $factory)
            ->afterGetConfigured($subject, $enforcing);

        $this->assertSame($relaxed, $result);
        $this->assertTrue($result->isReportOnly());
    }

    public function testLeavesAlreadyReportOnlyModeUntouched(): void
    {
        $subject = $this->createMock(ModeConfigManagerInterface::class);

        $reportOnly = $this->createMock(ModeConfiguredInterface::class);
        $reportOnly->method('isReportOnly')->willReturn(true);

        $factory = $this->createMock(ModeConfiguredFactory::class);
        $factory->expects($this->never())->method('create');

        $result = $this->makePlugin(hmr: true, factory: $factory)
            ->afterGetConfigured($subject, $reportOnly);

        $this->assertSame($reportOnly, $result);
    }

    public function testLeavesEnforcingModeUntouchedWhenHmrDisabled(): void
    {
        $subject = $this->createMock(ModeConfigManagerInterface::class);

        $enforcing = $this->createMock(ModeConfiguredInterface::class);
        $enforcing->method('isReportOnly')->willReturn(false);

        $factory = $this->createMock(ModeConfiguredFactory::class);
        $factory->expects($this->never())->method('create');

        $result = $this->makePlugin(hmr: false, factory: $factory)
            ->afterGetConfigured($subject, $enforcing);

        $this->assertSame($enforcing, $result);
    }

    private function makePlugin(bool $hmr, ModeConfiguredFactory $factory): ForceReportOnlyInHmr
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isHmrEnabled')->willReturn($hmr);

        return new ForceReportOnlyInHmr($configProvider, $factory);
    }
}
