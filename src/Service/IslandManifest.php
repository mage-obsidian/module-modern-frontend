<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Asset\Repository;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolves the modulepreload set for a group of island components out of Vite's
 * build manifest (`<generated>/.vite/manifest.json`).
 *
 * Given each component's output chunk, it walks the manifest's static `imports`
 * graph transitively and returns the full closure of chunk files to preload, so
 * the serial import waterfall (component → customer-data → section-store →
 * store → pinia/vue) is fetched in parallel instead. The manifest is read from
 * the theme source (works in any deploy mode) and cached for the process; any
 * failure degrades to an empty set so the page never breaks over a perf hint.
 *
 * Returns output-relative chunk paths (not URLs) so the caller can turn them
 * into versioned URLs with its own resolver — this keeps the service free of a
 * dependency on ViteResolver (which depends on this one).
 */
class IslandManifest
{
    private const MANIFEST_FILE = '.vite/manifest.json';

    private bool $loaded = false;

    /** @var array<string, mixed>|null */
    private ?array $manifest = null;

    public function __construct(
        private readonly Repository $assetRepository,
        private readonly ConfigProvider $configProvider,
        private readonly File $fileDriver,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * The given component chunks plus their transitive static dependency chunks,
     * deduplicated, as output-relative paths.
     *
     * @param string[] $componentFiles Output-relative chunk paths (manifest `file` values).
     *
     * @return string[]
     */
    public function getPreloadFiles(array $componentFiles): array
    {
        $manifest = $this->loadManifest();
        if (!$manifest || !$componentFiles) {
            return [];
        }

        return self::collectPreloadFiles($manifest, $componentFiles);
    }

    /**
     * Transitive closure of output chunk files reachable from the start chunks
     * through the manifest's static `imports` edges (including the start chunks
     * themselves). Pure: testable without a Magento install.
     *
     * @param array<string, mixed> $manifest Decoded Vite manifest.
     * @param string[] $startFiles Output-relative chunk paths to seed from.
     *
     * @return string[]
     */
    public static function collectPreloadFiles(array $manifest, array $startFiles): array
    {
        $keyByFile = [];
        foreach ($manifest as $key => $entry) {
            if (is_array($entry) && isset($entry['file']) && is_string($entry['file'])) {
                $keyByFile[$entry['file']] = $key;
            }
        }

        $stack = [];
        foreach ($startFiles as $file) {
            $file = self::ensureJsExtension($file);
            if (isset($keyByFile[$file])) {
                $stack[] = $keyByFile[$file];
            }
        }

        $files = [];
        $visited = [];
        while ($stack) {
            $key = array_pop($stack);
            if (isset($visited[$key])) {
                continue;
            }
            $visited[$key] = true;

            $entry = $manifest[$key] ?? null;
            if (!is_array($entry)) {
                continue;
            }
            if (isset($entry['file']) && is_string($entry['file'])) {
                $files[$entry['file']] = true;
            }
            foreach (($entry['imports'] ?? []) as $import) {
                if (is_string($import) && !isset($visited[$import])) {
                    $stack[] = $import;
                }
            }
        }

        return array_keys($files);
    }

    /**
     * Decoded manifest, or an empty array when unavailable. Cached for the
     * process. Skipped under HMR, where assets come from the dev server and no
     * build manifest exists.
     *
     * @return array<string, mixed>
     */
    private function loadManifest(): array
    {
        if ($this->loaded) {
            return $this->manifest ?? [];
        }
        $this->loaded = true;

        if ($this->configProvider->isHmrEnabled()) {
            return $this->manifest = [];
        }

        try {
            $fileId = $this->configProvider->getViteGeneratedPath() . '/' . self::MANIFEST_FILE;
            $source = $this->assetRepository->createAsset($fileId)->getSourceFile();
            if (!$this->fileDriver->isExists($source)) {
                return $this->manifest = [];
            }
            $decoded = json_decode($this->fileDriver->fileGetContents($source), true);

            return $this->manifest = is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            $this->logger->warning(
                'MageObsidian: could not read the Vite manifest for island modulepreload: ' . $e->getMessage()
            );

            return $this->manifest = [];
        }
    }

    private static function ensureJsExtension(string $file): string
    {
        return pathinfo($file, PATHINFO_EXTENSION) ? $file : $file . '.js';
    }
}
