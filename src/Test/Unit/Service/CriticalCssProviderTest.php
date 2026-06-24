<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Asset\File as AssetFile;
use Magento\Framework\View\Asset\Repository;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Service\CriticalCssProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Mocks Magento framework types, so it runs in a Magento root (see phpunit.xml)
 * and is excluded from the standalone CI suite.
 */
class CriticalCssProviderTest extends TestCase
{
    public function testReturnsFileContentsWhenPresent(): void
    {
        $assetRepository = $this->assetRepositoryReturning('/t/critical/cms_index_index.css');
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturn('.hero{color:#000}');

        $provider = $this->buildProvider($assetRepository, $this->configProvider(false), $fileDriver);

        $this->assertSame('.hero{color:#000}', $provider->getCriticalCss('cms_index_index'));
    }

    public function testRejectsHandleWithUnsafeCharacters(): void
    {
        $assetRepository = $this->createMock(Repository::class);
        $assetRepository->expects($this->never())->method('createAsset');

        $provider = $this->buildProvider(
            $assetRepository,
            $this->configProvider(false),
            $this->createMock(File::class)
        );

        $this->assertSame('', $provider->getCriticalCss('../../etc/env'));
    }

    public function testSkipsUnderHmr(): void
    {
        $assetRepository = $this->createMock(Repository::class);
        $assetRepository->expects($this->never())->method('createAsset');

        $provider = $this->buildProvider($assetRepository, $this->configProvider(true), $this->createMock(File::class));

        $this->assertSame('', $provider->getCriticalCss('cms_index_index'));
    }

    public function testReturnsEmptyWhenFileMissing(): void
    {
        $assetRepository = $this->assetRepositoryReturning('/t/critical/cms_index_index.css');
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(false);

        $provider = $this->buildProvider($assetRepository, $this->configProvider(false), $fileDriver);

        $this->assertSame('', $provider->getCriticalCss('cms_index_index'));
    }

    public function testDegradesAndLogsOnFailure(): void
    {
        $assetRepository = $this->createMock(Repository::class);
        $assetRepository->method('createAsset')->willThrowException(new RuntimeException('boom'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $provider = $this->buildProvider(
            $assetRepository,
            $this->configProvider(false),
            $this->createMock(File::class),
            $logger
        );

        $this->assertSame('', $provider->getCriticalCss('cms_index_index'));
    }

    public function testCachesPerHandle(): void
    {
        $assetRepository = $this->assetRepositoryReturning('/t/critical/cms_index_index.css');
        $assetRepository->expects($this->once())->method('createAsset');
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturn('.hero{}');

        $provider = $this->buildProvider($assetRepository, $this->configProvider(false), $fileDriver);

        $provider->getCriticalCss('cms_index_index');
        $provider->getCriticalCss('cms_index_index');
    }

    private function assetRepositoryReturning(string $sourceFile): Repository
    {
        $asset = $this->createMock(AssetFile::class);
        $asset->method('getSourceFile')->willReturn($sourceFile);
        $assetRepository = $this->createMock(Repository::class);
        $assetRepository->method('createAsset')->willReturn($asset);

        return $assetRepository;
    }

    private function configProvider(bool $hmr): ConfigProvider
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isHmrEnabled')->willReturn($hmr);
        $configProvider->method('getViteGeneratedPath')->willReturn('generated');

        return $configProvider;
    }

    private function buildProvider(
        Repository $assetRepository,
        ConfigProvider $configProvider,
        File $fileDriver,
        ?LoggerInterface $logger = null
    ): CriticalCssProvider {
        return new CriticalCssProvider(
            $assetRepository,
            $configProvider,
            $fileDriver,
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }
}
