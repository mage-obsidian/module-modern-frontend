<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Dev;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Service\Dev\DevServerProcess;
use MageObsidian\ModernFrontend\Service\Dev\DevWorkflow;
use MageObsidian\ModernFrontend\Service\Dev\HttpProberInterface;
use MageObsidian\ModernFrontend\Service\Dev\ProbeResult;
use MageObsidian\ModernFrontend\Service\Dev\ViteEnvFile;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DevWorkflowTest extends TestCase
{
    private WriterInterface&MockObject $configWriter;
    private TypeListInterface&MockObject $cacheTypeList;
    private DevServerProcess&MockObject $devServerProcess;
    private HttpProberInterface&MockObject $prober;
    private ConfigProvider&MockObject $configProvider;
    private DevWorkflow $workflow;

    protected function setUp(): void
    {
        $this->configWriter = $this->createMock(WriterInterface::class);
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);
        $this->devServerProcess = $this->createMock(DevServerProcess::class);
        $this->prober = $this->createMock(HttpProberInterface::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->configProvider->method('getViteEnvVars')->willReturn([
            ViteEnvFile::VAR_SERVER_HOST => 'vite',
            ViteEnvFile::VAR_SERVER_PORT => '5173',
        ]);

        $this->workflow = new DevWorkflow(
            $this->configWriter,
            $this->cacheTypeList,
            $this->devServerProcess,
            $this->prober,
            $this->configProvider
        );
    }

    public function testSetHmrEnabledPersistsFlagAndFlushesGatingCaches(): void
    {
        $this->configWriter->expects($this->once())
            ->method('save')
            ->with(ConfigProvider::HMR_ENABLED, '1');
        $this->cacheTypeList->expects($this->exactly(2))
            ->method('cleanType')
            ->willReturnCallback(function (string $type): void {
                $this->assertContains($type, ['config', 'full_page']);
            });

        $this->workflow->setHmr(true);
    }

    public function testSetHmrDisabledPersistsZero(): void
    {
        $this->configWriter->expects($this->once())
            ->method('save')
            ->with(ConfigProvider::HMR_ENABLED, '0');

        $this->workflow->setHmr(false);
    }

    public function testIsDevServerReachableProbesConfiguredUrl(): void
    {
        $this->prober->expects($this->once())
            ->method('probe')
            ->with('http://vite:5173/@vite/client')
            ->willReturn(new ProbeResult(true, 200, 'application/javascript'));

        $this->assertTrue($this->workflow->isDevServerReachable());
    }

    public function testIsDevServerReachableFalseWhenNotJavaScript(): void
    {
        $this->prober->method('probe')->willReturn(new ProbeResult(false, 502, 'text/html', 'bad gateway'));

        $this->assertFalse($this->workflow->isDevServerReachable());
    }

    public function testEnsureRunningLeavesAReachableServerAlone(): void
    {
        $this->prober->method('probe')->willReturn(new ProbeResult(true, 200, 'application/javascript'));
        $this->devServerProcess->expects($this->never())->method('start');

        $this->assertSame(['action' => 'already-running'], $this->workflow->ensureDevServerRunning('Acme/aurora', false));
    }

    public function testEnsureRunningSkipsWhenUnreachableAndNoStart(): void
    {
        $this->prober->method('probe')->willReturn(new ProbeResult(false, 0, '', 'refused'));
        $this->devServerProcess->expects($this->never())->method('start');

        $this->assertSame(['action' => 'skipped'], $this->workflow->ensureDevServerRunning('Acme/aurora', true));
    }

    public function testEnsureRunningSpawnsWhenUnreachable(): void
    {
        $this->prober->method('probe')->willReturn(new ProbeResult(false, 0, '', 'refused'));
        $info = ['pid' => 4321, 'theme' => 'Acme/aurora', 'log' => '/var/log/dev.log'];
        $this->devServerProcess->expects($this->once())
            ->method('start')
            ->with('Acme/aurora')
            ->willReturn($info);

        $this->assertSame(
            ['action' => 'started', 'info' => $info],
            $this->workflow->ensureDevServerRunning('Acme/aurora', false)
        );
    }
}
