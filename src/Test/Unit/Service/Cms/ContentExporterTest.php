<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Cms;

use ArrayIterator;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Block\Collection as BlockCollection;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\Collection as PageCollection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use MageObsidian\ModernFrontend\Service\Cms\ContentExporter;
use PHPUnit\Framework\TestCase;

/**
 * What the export leaves behind is the baseline the runtime measures against,
 * so the two things worth pinning down are that excluded content contributes
 * nothing at all, and that two identifiers can never land on the same file —
 * a collision would silently drop one page's classes from the build.
 */
class ContentExporterTest extends TestCase
{
    /** @var array<string, string> */
    private array $written = [];

    private File $fileDriver;

    protected function setUp(): void
    {
        $this->written = [];

        $this->fileDriver = $this->createMock(File::class);
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('filePutContents')
            ->willReturnCallback(function (string $path, string $contents): int {
                $this->written[$path] = $contents;

                return strlen($contents);
            });
    }

    /**
     * @param array<int, array{string, string, bool}> $pages
     * @param array<int, array{string, string, bool}> $blocks
     */
    private function exporter(array $pages, array $blocks = []): ContentExporter
    {
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getPath')->willReturn('/var');

        return new ContentExporter(
            $this->collectionFactory(PageCollectionFactory::class, PageCollection::class, Page::class, $pages),
            $this->collectionFactory(BlockCollectionFactory::class, BlockCollection::class, Block::class, $blocks),
            $directoryList,
            $this->fileDriver
        );
    }

    private function collectionFactory(string $factory, string $collection, string $model, array $rows): object
    {
        $items = [];
        foreach ($rows as [$identifier, $content, $skip]) {
            $item = $this->createMock($model);
            $item->method('getIdentifier')->willReturn($identifier);
            $item->method('getContent')->willReturn($content);
            $item->method('getData')->with(ContentExporter::SKIP_FLAG)->willReturn($skip ? 1 : 0);
            $items[] = $item;
        }

        $collectionMock = $this->createMock($collection);
        $collectionMock->method('getIterator')->willReturn(new ArrayIterator($items));

        $factoryMock = $this->createMock($factory);
        $factoryMock->method('create')->willReturn($collectionMock);

        return $factoryMock;
    }

    private function candidates(): array
    {
        $path = '/var/mage-obsidian/cms/candidates.json';
        $this->assertArrayHasKey($path, $this->written);

        return json_decode($this->written[$path], true);
    }

    public function testWritesEveryPageAndBlockAndCollectsTheirClasses(): void
    {
        $result = $this->exporter(
            [['about-us', '<div class="prose">A</div>', false]],
            [['footer-links', '<ul class="grid gap-2">B</ul>', false]]
        )->export();

        $this->assertSame(1, $result['pages']);
        $this->assertSame(1, $result['blocks']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(['gap-2', 'grid', 'prose'], $this->candidates());
    }

    public function testExcludedContentIsNeitherWrittenNorCounted(): void
    {
        $result = $this->exporter([
            ['about-us', '<div class="prose">A</div>', false],
            ['embedded-widget', '<div class="third-party-junk">B</div>', true],
        ])->export();

        $this->assertSame(1, $result['pages']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(['prose'], $this->candidates());
        $this->assertStringNotContainsString('third-party-junk', implode('', $this->written));
    }

    public function testTwoIdentifiersThatFlattenAlikeGetDifferentFiles(): void
    {
        $this->exporter([
            ['help/shipping', '<i class="a"></i>', false],
            ['help-shipping', '<i class="b"></i>', false],
        ])->export();

        $files = array_filter(array_keys($this->written), static fn ($p) => str_contains($p, '/pages/'));

        $this->assertCount(2, $files);
        $this->assertSame(['a', 'b'], $this->candidates());
    }

    public function testAnEmptyStoreStillLeavesAReadableBaseline(): void
    {
        $result = $this->exporter([])->export();

        $this->assertSame(0, $result['pages']);
        $this->assertSame([], $this->candidates());
    }

    public function testFileNameFlattensAnIdentifierWithoutEscapingTheDirectory(): void
    {
        $name = ContentExporter::fileName('../../etc/env.php');

        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('.', $name);
        $this->assertNotSame(ContentExporter::fileName('etc-env-php'), $name);
    }
}
