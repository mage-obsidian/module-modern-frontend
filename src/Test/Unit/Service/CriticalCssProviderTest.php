<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Asset\File as AssetFile;
use Magento\Framework\View\Asset\File\NotFoundException;
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

    public function testStaysSilentWhenNoCriticalWasBuiltForHandle(): void
    {
        $asset = $this->createMock(AssetFile::class);
        $asset->method('getSourceFile')->willThrowException(
            new NotFoundException("Unable to resolve the source file for 'generated/critical/x.css'")
        );
        $assetRepository = $this->createMock(Repository::class);
        $assetRepository->method('createAsset')->willReturn($asset);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $provider = $this->buildProvider(
            $assetRepository,
            $this->configProvider(false),
            $this->createMock(File::class),
            $logger
        );

        $this->assertSame('', $provider->getCriticalCss('catalog_category_view'));
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

    public function testPointsRelativeFontUrlsAtTheStaticPathOfTheServingStore(): void
    {
        $assetRepository = $this->assetRepositoryReturning('/t/web/critical/cms_index_index.css');
        $assetRepository->expects($this->once())
            ->method('getUrlWithParams')
            ->with('generated/font-a.woff2', ['_secure' => true])
            ->willReturn('https://shop.test/static/version7/frontend/Acme/shop/es_ES/generated/font-a.woff2');
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturn("@font-face{src:url('../font-a.woff2')}");

        $provider = $this->buildProvider($assetRepository, $this->configProvider(false), $fileDriver);

        $this->assertSame(
            '@font-face{src:url(/static/version7/frontend/Acme/shop/es_ES/generated/font-a.woff2)}',
            $provider->getCriticalCss('cms_index_index')
        );
    }

    public function testLeavesFontUrlsAnOlderReleaseAlreadyResolvedAlone(): void
    {
        $css = '@font-face{src:url(/static/version1/frontend/Acme/shop/en_US/generated/font-a.woff2)}';
        $assetRepository = $this->assetRepositoryReturning('/t/web/generated/critical/cms_index_index.css');
        $assetRepository->expects($this->never())->method('getUrlWithParams');
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturn($css);

        $provider = $this->buildProvider($assetRepository, $this->configProvider(false), $fileDriver);

        $this->assertSame($css, $provider->getCriticalCss('cms_index_index'));
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

    public function testPrefersTheThemeSourceCriticalOverTheOldGeneratedOne(): void
    {
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturnCallback(
            fn (string $path): string => $path === '/t/web/critical/cms_index_index.css' ? '.new{}' : '.old{}'
        );

        $provider = $this->buildProvider(
            $this->assetRepositoryMapping([
                'critical/cms_index_index.css' => '/t/web/critical/cms_index_index.css',
                'generated/critical/cms_index_index.css' => '/t/web/generated/critical/cms_index_index.css',
            ]),
            $this->configProvider(false),
            $fileDriver
        );

        $this->assertSame('.new{}', $provider->getCriticalCss('cms_index_index'));
    }

    public function testFallsBackToTheOldGeneratedCritical(): void
    {
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturn('.old{}');

        $provider = $this->buildProvider(
            $this->assetRepositoryMapping([
                'generated/critical/cms_index_index.css' => '/t/web/generated/critical/cms_index_index.css',
            ]),
            $this->configProvider(false),
            $fileDriver
        );

        $this->assertSame('.old{}', $provider->getCriticalCss('cms_index_index'));
    }

    public function testReadsOnlyTheNewLocationWhenTheOldOneIsGone(): void
    {
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturn('.new{}');

        $provider = $this->buildProvider(
            $this->assetRepositoryMapping(['critical/cms_index_index.css' => '/t/web/critical/cms_index_index.css']),
            $this->configProvider(false),
            $fileDriver
        );

        $this->assertSame('.new{}', $provider->getCriticalCss('cms_index_index'));
    }

    private function assetRepositoryMapping(array $sources): Repository
    {
        $repository = $this->createMock(Repository::class);
        $repository->method('createAsset')->willReturnCallback(function (string $fileId) use ($sources): AssetFile {
            $asset = $this->createMock(AssetFile::class);
            if (isset($sources[$fileId])) {
                $asset->method('getSourceFile')->willReturn($sources[$fileId]);
            } else {
                $asset->method('getSourceFile')->willThrowException(new NotFoundException('missing ' . $fileId));
            }
            return $asset;
        });

        return $repository;
    }
}
