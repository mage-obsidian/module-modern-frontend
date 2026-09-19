<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\App\DeploymentConfig;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Plugin\App\DeploymentConfig\WriterPlugin;
use Magento\Framework\App\DeploymentConfig\Writer;
use Magento\Framework\Config\File\ConfigFilePool;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class WriterPluginTest extends TestCase
{
    public function testWritingOnlyEnvPhpLeavesTheContractAlone(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->never())->method('generate');

        $plugin = new WriterPlugin($configManager, $this->createStub(LoggerInterface::class));

        $plugin->afterSaveConfig(
            $this->createStub(Writer::class),
            null,
            [ConfigFilePool::APP_ENV => ['MAGE_MODE' => 'production']]
        );
    }

    public function testWritingTheModuleListRegeneratesTheContract(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->once())->method('generate');

        $plugin = new WriterPlugin($configManager, $this->createStub(LoggerInterface::class));

        $plugin->afterSaveConfig(
            $this->createStub(Writer::class),
            null,
            [ConfigFilePool::APP_CONFIG => ['modules' => ['Vendor_Mod' => 1]]]
        );
    }

    public function testAFailedRegenerationNeverFailsTheConfigWrite(): void
    {
        $configManager = $this->createStub(ConfigManagerInterface::class);
        $configManager->method('generate')->willThrowException(new RuntimeException('no database'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            $this->stringContains('mage-obsidian:frontend:config --generate')
        );

        $plugin = new WriterPlugin($configManager, $logger);

        $this->assertSame(
            'result',
            $plugin->afterSaveConfig(
                $this->createStub(Writer::class),
                'result',
                [ConfigFilePool::APP_CONFIG => ['modules' => ['Vendor_Mod' => 1]]]
            )
        );
    }
}
