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
use PHPUnit\Framework\MockObject\MockObject;
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
    private ContentExporter&MockObject $exporter;
    private CmsBaseline&MockObject $baseline;
    private TailwindCli&MockObject $tailwind;
    private CacheInterface&MockObject $cache;
    private LockManagerInterface&MockObject $lockManager;
    private WriteInterface&MockObject $mediaWrite;
    private ReadInterface&MockObject $mediaRead;

    /** @var array<string, string> */
    private array $written = [];

    protected function setUp(): void
    {
        $this->written = [];
        $this->exporter = $this->createMock(ContentExporter::class);
        $this->baseline = $this->createMock(CmsBaseline::class);
        $this->tailwind = $this->createMock(TailwindCli::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->lockManager->method('lock')->willReturn(true);

        $this->mediaWrite = $this->createMock(WriteInterface::class);
        $this->mediaWrite->method('writeFile')->willReturnCallback(
            function (string $path, string $contents): int {
                $this->written[$path] = $contents;

                return strlen($contents);
            }
        );
        $this->mediaRead = $this->createMock(ReadInterface::class);
    }

    private function service(): DeltaStylesheet
    {
        $theme = $this->createMock(ThemeInterface::class);
        $theme->method('getCode')->willReturn('Vendor/theme');
        $theme->method('getId')->willReturn(1);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('1');

        $themeProvider = $this->createMock(ThemeProviderInterface::class);
        $themeProvider->method('getThemeById')->willReturn($theme);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($this->mediaWrite);
        $filesystem->method('getDirectoryRead')->willReturn($this->mediaRead);

        $asset = $this->createMock(AssetFile::class);
        $asset->method('getSourceFile')->willReturn('/theme/web/css/theme.source.css');
        $assetRepository = $this->createMock(Repository::class);
        $assetRepository->method('createAsset')->willReturn($asset);

        $appState = $this->createMock(State::class);
        $appState->method('emulateAreaCode')->willReturnCallback(
            static fn (string $area, callable $callback) => $callback()
        );

        return new DeltaStylesheet(
            $this->exporter,
            $this->baseline,
            $this->tailwind,
            $filesystem,
            $assetRepository,
            $this->createMock(DesignInterface::class),
            $themeProvider,
            $storeManager,
            $scopeConfig,
            $appState,
            $this->cache,
            $this->lockManager,
            $this->createMock(LoggerInterface::class)
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
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(false);
        $this->lockManager = $lockManager;
        $this->exporter->expects($this->never())->method('collectCandidates');
        $this->cache->method('load')->willReturn(json_encode(['classes' => 4, 'bytes' => 900, 'hash' => 'abc']));

        $result = $this->service()->regenerate();

        $this->assertTrue($result['skipped']);
        $this->assertSame(4, $result['classes']);
    }

    public function testFallsBackToTheFileWhenTheCacheWasFlushed(): void
    {
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
}
