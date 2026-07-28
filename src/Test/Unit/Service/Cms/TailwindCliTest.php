<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Cms;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use MageObsidian\ModernFrontend\Service\Cms\TailwindCli;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Resolving the binary and refusing to run without it are the parts that decide
 * whether saving a page can break. Actually invoking Tailwind is covered by the
 * end-to-end run, not here — a unit test that shells out is a unit test that
 * fails on someone else's machine.
 */
class TailwindCliTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private File&MockObject $fileDriver;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function cli(): TailwindCli
    {
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn('/magento');
        $directoryList->method('getPath')->willReturn('/magento/var');

        return new TailwindCli($this->scopeConfig, $directoryList, $this->fileDriver, $this->logger);
    }

    public function testDefaultsToBinTailwindcssInTheMagentoRoot(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame('/magento/bin/tailwindcss', $this->cli()->getBinaryPath());
    }

    public function testAConfiguredRelativePathIsResolvedFromTheRoot(): void
    {
        $this->scopeConfig->method('getValue')->with(TailwindCli::CONFIG_PATH)->willReturn('tools/tw');

        $this->assertSame('/magento/tools/tw', $this->cli()->getBinaryPath());
    }

    public function testAConfiguredAbsolutePathIsUsedAsGiven(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('/opt/tailwindcss');

        $this->assertSame('/opt/tailwindcss', $this->cli()->getBinaryPath());
    }

    public function testIsNotAvailableWhenTheBinaryIsMissing(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->fileDriver->method('isExists')->willReturn(false);

        $cli = $this->cli();

        $this->assertFalse($cli->isAvailable());
        $this->assertSame('', $cli->getVersion());
    }

    public function testCompilingWithoutTheBinaryYieldsNothingRatherThanAnError(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->fileDriver->expects($this->never())->method('filePutContents');

        $this->assertSame('', $this->cli()->compile(['p-4'], '/theme/theme.source.css'));
    }

    public function testCompilingNothingSkipsTheBinaryEntirely(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->fileDriver->expects($this->never())->method('isExists');

        $this->assertSame('', $this->cli()->compile([], '/theme/theme.source.css'));
    }
}
