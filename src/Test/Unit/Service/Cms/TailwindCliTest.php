<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Cms;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Phrase;
use MageObsidian\ModernFrontend\Service\Cms\TailwindCli;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Resolving the binary and refusing to run without it are the parts that decide
 * whether saving a page can break. Actually invoking Tailwind is covered by the
 * end-to-end run, not here — a unit test that shells out is a unit test that
 * fails on someone else's machine.
 */
class TailwindCliTest extends TestCase
{
    private ScopeConfigInterface&Stub $scopeConfig;
    private File&Stub $fileDriver;
    private LoggerInterface&Stub $logger;
    private string $binary = '';

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $this->fileDriver = $this->createStub(File::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        if ($this->binary !== '' && is_file($this->binary)) {
            unlink($this->binary);
        }
    }

    private function cli(): TailwindCli
    {
        $directoryList = $this->createStub(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn('/magento');
        $directoryList->method('getPath')->willReturn('/magento/var');

        return new TailwindCli($this->scopeConfig, $directoryList, $this->fileDriver, $this->logger);
    }

    private function binary(int $mode): string
    {
        $this->binary = (string)tempnam(sys_get_temp_dir(), 'tailwind');
        chmod($this->binary, $mode);
        $this->scopeConfig->method('getValue')->willReturn($this->binary);
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);

        return $this->binary;
    }

    private function cliRunning(Process $process): TailwindCli
    {
        $directoryList = $this->createStub(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn('/magento');
        $directoryList->method('getPath')->willReturn('/magento/var');

        return new class ($this->scopeConfig, $directoryList, $this->fileDriver, $this->logger, $process) extends TailwindCli {
            public function __construct(
                ScopeConfigInterface $scopeConfig,
                DirectoryList $directoryList,
                File $fileDriver,
                LoggerInterface $logger,
                private readonly Process $stub
            ) {
                parent::__construct($scopeConfig, $directoryList, $fileDriver, $logger);
            }

            protected function createProcess(array $command): Process
            {
                return $this->stub;
            }
        };
    }

    public function testDefaultsToBinTailwindcssInTheMagentoRoot(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame('/magento/bin/tailwindcss', $this->cli()->getBinaryPath());
    }

    public function testAConfiguredRelativePathIsResolvedFromTheRoot(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('tools/tw');

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

    public function testCompilingWithoutTheBinaryIsAFailureNotAnEmptyDelta(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->fileDriver = $this->createMock(File::class);
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->fileDriver->expects($this->never())->method('filePutContents');

        $this->assertNull($this->cli()->compile(['p-4'], '/theme/theme.source.css'));
    }

    public function testABinaryThatCannotBeExecutedIsAFailure(): void
    {
        $this->binary(0644);

        $this->assertNull($this->cli()->compile(['p-4'], '/theme/theme.source.css'));
    }

    public function testAProcessThatFailsIsAFailure(): void
    {
        $this->binary(0755);
        $process = $this->createStub(Process::class);
        $process->method('isSuccessful')->willReturn(false);
        $process->method('getErrorOutput')->willReturn('boom');
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())->method('warning');

        $this->assertNull($this->cliRunning($process)->compile(['p-4'], '/theme/theme.source.css'));
    }

    public function testATimeoutIsAFailure(): void
    {
        $this->binary(0755);
        $process = $this->createStub(Process::class);
        $process->method('run')->willThrowException(
            new ProcessTimedOutException($process, ProcessTimedOutException::TYPE_GENERAL)
        );

        $this->assertNull($this->cliRunning($process)->compile(['p-4'], '/theme/theme.source.css'));
    }

    public function testACleanupThatFailsDoesNotLoseTheCompiledCss(): void
    {
        $this->binary(0755);
        $process = $this->createStub(Process::class);
        $process->method('isSuccessful')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('.p-4{padding:1rem}');
        $this->fileDriver->method('deleteFile')->willThrowException(new FileSystemException(new Phrase('locked')));
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->atLeastOnce())->method('warning');

        $this->assertSame('.p-4{padding:1rem}', $this->cliRunning($process)->compile(['p-4'], '/theme/theme.source.css'));
    }

    public function testASuccessfulRunReturnsTheCss(): void
    {
        $this->binary(0755);
        $process = $this->createStub(Process::class);
        $process->method('isSuccessful')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('.p-4{padding:1rem}');

        $this->assertSame('.p-4{padding:1rem}', $this->cliRunning($process)->compile(['p-4'], '/theme/theme.source.css'));
    }

    public function testCompilingNothingSkipsTheBinaryEntirely(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->fileDriver = $this->createMock(File::class);
        $this->fileDriver->expects($this->never())->method('isExists');

        $this->assertSame('', $this->cli()->compile([], '/theme/theme.source.css'));
    }
}
