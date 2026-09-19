<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Cms;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\View\Asset\File as AssetFile;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\ModernFrontend\Service\Cms\CmsBaseline;
use MageObsidian\ModernFrontend\Service\Cms\ContentExporter;
use MageObsidian\ModernFrontend\Service\Cms\DeltaStylesheet;
use MageObsidian\ModernFrontend\Service\Cms\TailwindCli;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The subtraction is the whole idea: what the build already covers must never be
 * compiled again, and after a build the difference has to come out empty on its
 * own. The rest of what is pinned here is what happens when it cannot — a lock
 * held elsewhere, a class Tailwind does not know, a cache that was flushed.
 */
class DeltaStylesheetTest extends TestCase
{
    private ContentExporter&Stub $exporter;
    private CmsBaseline&Stub $baseline;
    private TailwindCli&Stub $tailwind;
    private CacheInterface&Stub $cache;
    private LockManagerInterface&Stub $lockManager;
    private WriteInterface&Stub $mediaWrite;
    private ReadInterface&Stub $mediaRead;
    private LoggerInterface&Stub $logger;

    /** @var array<string, string> */
    private array $written = [];

    protected function setUp(): void
    {
        $this->written = [];
        $this->exporter = $this->createStub(ContentExporter::class);
        $this->baseline = $this->createStub(CmsBaseline::class);
        $this->tailwind = $this->createStub(TailwindCli::class);
        $this->cache = $this->createStub(CacheInterface::class);
        $this->lockManager = $this->createStub(LockManagerInterface::class);
        $this->lockManager->method('lock')->willReturn(true);

        $this->mediaWrite = $this->createStub(WriteInterface::class);
        $this->mediaWrite->method('writeFile')->willReturnCallback(
            function (string $path, string $contents): int {
                $this->written[$path] = $contents;

                return strlen($contents);
            }
        );
        $this->mediaRead = $this->createStub(ReadInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function service(): DeltaStylesheet
    {
        $theme = $this->createStub(ThemeInterface::class);
        $theme->method('getCode')->willReturn('Vendor/theme');
        $theme->method('getId')->willReturn(1);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('1');

        $themeProvider = $this->createStub(ThemeProviderInterface::class);
        $themeProvider->method('getThemeById')->willReturn($theme);

        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($this->mediaWrite);
        $filesystem->method('getDirectoryRead')->willReturn($this->mediaRead);

        $asset = $this->createStub(AssetFile::class);
        $asset->method('getSourceFile')->willReturn('/theme/web/css/theme.source.css');
        $assetRepository = $this->createStub(Repository::class);
        $assetRepository->method('createAsset')->willReturn($asset);

        $appState = $this->createStub(State::class);
        $appState->method('emulateAreaCode')->willReturnCallback(
            static fn (string $area, callable $callback) => $callback()
        );

        return new DeltaStylesheet(
            $this->exporter,
            $this->baseline,
            $this->tailwind,
            $filesystem,
            $assetRepository,
            $this->createStub(DesignInterface::class),
            $themeProvider,
            $storeManager,
            $scopeConfig,
            $appState,
            $this->cache,
            $this->lockManager,
            $this->logger
        );
    }

    private function cssPath(): string
    {
        return 'mage-obsidian/cms/Vendor_theme/on-the-fly.css';
    }

    public function testCompilesOnlyWhatTheBuildDoesNotAlreadyCover(): void
    {
        $this->exporter->method('collectCandidates')->willReturn(['bg-red-500', 'p-4', 'text-sm']);
        $this->baseline->method('read')->willReturn(['p-4', 'text-sm', 'flex']);
        $this->tailwind = $this->createMock(TailwindCli::class);
        $this->tailwind->expects($this->once())
            ->method('compile')
            ->with(['bg-red-500'], '/theme/web/css/theme.source.css')
            ->willReturn('@layer utilities { .bg-red-500 { color: red } }');

        $result = $this->service()->regenerate();

        $this->assertSame(1, $result['classes']);
        $this->assertSame(1, $result['themes']);
        $this->assertFalse($result['skipped']);
        $this->assertStringContainsString('.bg-red-500', $this->written[$this->cssPath()]);
    }

    public function testABuildThatCoversEverythingLeavesAnEmptyFileAndNeverCompiles(): void
    {
        $this->exporter->method('collectCandidates')->willReturn(['p-4', 'text-sm']);
        $this->baseline->method('read')->willReturn(['p-4', 'text-sm', 'flex']);
        $this->tailwind = $this->createMock(TailwindCli::class);
        $this->tailwind->expects($this->never())->method('compile');

        $result = $this->service()->regenerate();

        $this->assertSame(0, $result['classes']);
        $this->assertSame(0, $result['bytes']);
        $this->assertSame('', $this->written[$this->cssPath()]);
    }

    public function testNamesTheClassesTailwindGeneratedNoRuleFor(): void
    {
        $this->exporter->method('collectCandidates')->willReturn(['md:grid-cols-3', 'block-promo', 'p-[13px]']);
        $this->baseline->method('read')->willReturn([]);
        $this->tailwind->method('compile')->willReturn(
            '@layer utilities { .p-\[13px\] { padding: 13px } .md\:grid-cols-3 { display: grid } }'
        );

        $result = $this->service()->regenerate();

        $this->assertSame(['block-promo'], $result['unresolved']);
    }

    public function testDoesNotTouchTheDatabaseWhileAnotherProcessHoldsTheLock(): void
    {
        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(false);
        $this->lockManager = $lockManager;
        $this->exporter = $this->createMock(ContentExporter::class);
        $this->exporter->expects($this->never())->method('collectCandidates');
        $this->cache->method('load')->willReturn(json_encode(['classes' => 4, 'bytes' => 900, 'hash' => 'abc']));

        $result = $this->service()->regenerate();

        $this->assertTrue($result['skipped']);
        $this->assertSame(4, $result['classes']);
    }

    public function testFallsBackToTheFileWhenTheCacheWasFlushed(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->method('load')->willReturn(false);
        $this->mediaRead->method('isExist')->willReturn(true);
        $this->mediaRead->method('readFile')->willReturn(
            (string)json_encode(['classes' => 2, 'unresolved' => [], 'bytes' => 326, 'hash' => 'deadbeef'])
        );
        $this->cache->expects($this->atLeastOnce())->method('save');

        $service = $this->service();

        $this->assertSame(326, $service->state('Vendor_theme')['bytes']);
        $this->assertTrue($service->hasDelta());
    }

    public function testReportsNoDeltaWhenNeitherCacheNorFileHasOne(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->mediaRead->method('isExist')->willReturn(false);

        $this->assertFalse($this->service()->hasDelta());
    }

    public function testABaselineIsMissingWhenAConfiguredThemeHasNone(): void
    {
        $this->baseline->method('exists')->willReturn(false);

        $this->assertFalse($this->service()->hasBaseline());
    }

    private function serviceWithThemes(array $codes): DeltaStylesheet
    {
        $stores = [];
        $themes = [];
        foreach (array_values($codes) as $index => $code) {
            $id = $index + 1;
            $store = $this->createStub(StoreInterface::class);
            $store->method('getId')->willReturn($id);
            $stores[] = $store;
            $theme = $this->createStub(ThemeInterface::class);
            $theme->method('getCode')->willReturn($code);
            $theme->method('getId')->willReturn($id);
            $themes[$id] = $theme;
        }
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path, string $scope, int $storeId): string => (string)$storeId
        );

        $themeProvider = $this->createStub(ThemeProviderInterface::class);
        $themeProvider->method('getThemeById')->willReturnCallback(static fn (int $id) => $themes[$id]);

        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($this->mediaWrite);
        $filesystem->method('getDirectoryRead')->willReturn($this->mediaRead);

        $asset = $this->createStub(AssetFile::class);
        $asset->method('getSourceFile')->willReturn('/theme/web/css/theme.source.css');
        $assetRepository = $this->createStub(Repository::class);
        $assetRepository->method('createAsset')->willReturn($asset);

        $appState = $this->createStub(State::class);
        $appState->method('emulateAreaCode')->willReturnCallback(
            static fn (string $area, callable $callback) => $callback()
        );

        return new DeltaStylesheet(
            $this->exporter,
            $this->baseline,
            $this->tailwind,
            $filesystem,
            $assetRepository,
            $this->createStub(DesignInterface::class),
            $themeProvider,
            $storeManager,
            $scopeConfig,
            $appState,
            $this->cache,
            $this->lockManager,
            $this->logger
        );
    }

    public function testAFailedCompileKeepsThePreviousDeltaWhenTheCacheIsWarm(): void
    {
        $this->exporter->method('collectCandidates')->willReturn(['bg-red-500']);
        $this->baseline->method('read')->willReturn([]);
        $this->tailwind->method('compile')->willReturn(null);
        $this->cache->method('load')->willReturn(
            (string)json_encode(['classes' => 1, 'unresolved' => [], 'bytes' => 48, 'hash' => 'previous'])
        );

        $result = $this->service()->regenerate();

        $this->assertSame([], $this->written);
        $this->assertSame(48, $result['bytes']);
    }

    public function testAFailedCompileKeepsThePreviousDeltaWhenTheCacheIsCold(): void
    {
        $this->exporter->method('collectCandidates')->willReturn(['bg-red-500']);
        $this->baseline->method('read')->willReturn([]);
        $this->tailwind->method('compile')->willReturn(null);
        $this->cache->method('load')->willReturn(false);
        $this->mediaRead->method('isExist')->willReturn(true);
        $this->mediaRead->method('readFile')->willReturn(
            (string)json_encode(['classes' => 1, 'unresolved' => [], 'bytes' => 48, 'hash' => 'previous'])
        );

        $result = $this->service()->regenerate();

        $this->assertSame([], $this->written);
        $this->assertSame(48, $result['bytes']);
    }

    public function testStaysQuietWhenTheBinaryIsSimplyNotInstalled(): void
    {
        $this->exporter->method('collectCandidates')->willReturn(['bg-red-500']);
        $this->baseline->method('read')->willReturn([]);
        $this->tailwind->method('compile')->willReturn(null);
        $this->tailwind->method('isAvailable')->willReturn(false);
        $this->cache->method('load')->willReturn(false);
        $this->mediaRead->method('isExist')->willReturn(false);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->never())->method('warning');

        $this->service()->regenerate();

        $this->assertSame([], $this->written);
    }

    public function testWarnsWhenTheBinaryIsInstalledButCompilingFailed(): void
    {
        $this->exporter->method('collectCandidates')->willReturn(['bg-red-500']);
        $this->baseline->method('read')->willReturn([]);
        $this->tailwind->method('compile')->willReturn(null);
        $this->tailwind->method('isAvailable')->willReturn(true);
        $this->cache->method('load')->willReturn(false);
        $this->mediaRead->method('isExist')->willReturn(false);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())->method('warning');

        $this->service()->regenerate();
    }

    public function testOneThemeFailingDoesNotStopTheNext(): void
    {
        $this->exporter->method('collectCandidates')->willReturn(['bg-red-500']);
        $this->baseline->method('read')->willReturn([]);
        $this->tailwind->method('compile')->willReturnOnConsecutiveCalls(null, '.bg-red-500{color:red}');
        $this->cache->method('load')->willReturn(false);
        $this->mediaRead->method('isExist')->willReturn(false);

        $result = $this->serviceWithThemes(['Vendor/one', 'Vendor/two'])->regenerate();

        $this->assertSame(2, $result['themes']);
        $this->assertArrayNotHasKey('mage-obsidian/cms/Vendor_one/on-the-fly.css', $this->written);
        $this->assertSame('.bg-red-500{color:red}', $this->written['mage-obsidian/cms/Vendor_two/on-the-fly.css']);
    }
}
