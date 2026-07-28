<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Cms;

use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;

/**
 * Writes CMS content to disk so Tailwind's scanner can see it.
 *
 * The scanner reads files; the content lives in a database, so classes an
 * author writes in a page or block are invisible to a build and end up missing
 * from the stylesheet. Exporting closes that gap for everything that exists
 * when the theme is built, and the class list it leaves behind is the baseline
 * the runtime measures against — anything authored later is the difference.
 *
 * The whole directory is rewritten on every run: it is derived from the
 * database, so a stale file for a deleted page would keep contributing classes
 * that nothing uses.
 */
class ContentExporter
{
    public const string PAGES_DIR = 'pages';
    public const string BLOCKS_DIR = 'blocks';
    public const string SKIP_FLAG = 'mage_obsidian_skip_css_scan';

    private const string EXTENSION = '.html';

    public function __construct(
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly DirectoryList $directoryList,
        private readonly File $fileDriver
    ) {
    }

    /**
     * @return array{pages: int, blocks: int, skipped: int, candidates: string[]}
     */
    public function export(): array
    {
        $root = $this->rootPath();
        if ($this->fileDriver->isExists($root)) {
            $this->fileDriver->deleteDirectory($root);
        }

        $written = ['pages' => 0, 'blocks' => 0, 'skipped' => 0];
        $candidates = [];

        foreach ([self::PAGES_DIR => $this->pages(), self::BLOCKS_DIR => $this->blocks()] as $dir => $rows) {
            foreach ($rows as $identifier => $content) {
                if ($content === null) {
                    $written['skipped']++;
                    continue;
                }
                $this->write($root . DIRECTORY_SEPARATOR . $dir, $identifier, $content);
                $written[$dir]++;
                $candidates[] = ClassCandidates::extract($content);
            }
        }

        $candidates = $candidates === [] ? [] : ClassCandidates::merge(...$candidates);
        $this->fileDriver->filePutContents(
            $this->candidatesPath(),
            json_encode($candidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $written + ['candidates' => $candidates];
    }

    /**
     * The class names the last export saw, or an empty list when there is none.
     *
     * @return string[]
     */
    public function readCandidates(): array
    {
        $path = $this->candidatesPath();
        if (!$this->fileDriver->isExists($path)) {
            return [];
        }

        $decoded = json_decode($this->fileDriver->fileGetContents($path), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    public function rootPath(): string
    {
        return $this->directoryList->getPath(DirectoryList::VAR_DIR)
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ConfigInterface::CMS_CONTENT_PATH);
    }

    public function candidatesPath(): string
    {
        return $this->rootPath() . DIRECTORY_SEPARATOR . ConfigInterface::CMS_CANDIDATES_FILE;
    }

    /**
     * Content by identifier, with `null` marking an entity excluded from the scan.
     *
     * @return array<string, string|null>
     */
    private function pages(): array
    {
        $rows = [];
        foreach ($this->pageCollectionFactory->create() as $page) {
            $rows[(string)$page->getIdentifier()] = $page->getData(self::SKIP_FLAG)
                ? null
                : (string)$page->getContent();
        }

        return $rows;
    }

    /**
     * @return array<string, string|null>
     */
    private function blocks(): array
    {
        $rows = [];
        foreach ($this->blockCollectionFactory->create() as $block) {
            $rows[(string)$block->getIdentifier()] = $block->getData(self::SKIP_FLAG)
                ? null
                : (string)$block->getContent();
        }

        return $rows;
    }

    private function write(string $dir, string $identifier, string $content): void
    {
        if (!$this->fileDriver->isDirectory($dir)) {
            $this->fileDriver->createDirectory($dir);
        }
        $this->fileDriver->filePutContents(
            $dir . DIRECTORY_SEPARATOR . self::fileName($identifier) . self::EXTENSION,
            $content
        );
    }

    /**
     * An identifier is merchant-typed and can carry slashes and dots, so it is
     * flattened rather than trusted as a path. Flattening can collide —
     * `a/b` and `a-b` reduce to the same thing — and a collision would silently
     * drop one entity's classes from the scan, so the digest keeps them apart.
     */
    public static function fileName(string $identifier): string
    {
        $name = trim(preg_replace('~[^A-Za-z0-9_-]+~', '-', $identifier) ?? '', '-');

        return ($name === '' ? 'untitled' : $name) . '-' . substr(hash('xxh128', $identifier), 0, 8);
    }
}
