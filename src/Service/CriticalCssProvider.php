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
 * Reads the per-handle critical CSS that the build wrote to
 * `<generated>/critical/<handle>.css` (see the `mage-obsidian:frontend:critical-css`
 * command). The content — not a URL — is returned so the head can inline it.
 *
 * Read from the theme source like the Vite manifest (works in any deploy mode),
 * cached per process, skipped under HMR, and degraded to an empty string on any
 * failure so a missing/unreadable file just falls back to the render-blocking
 * stylesheet rather than breaking the page.
 */
class CriticalCssProvider
{
    private const CRITICAL_DIR = 'critical';

    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(
        private readonly Repository $assetRepository,
        private readonly ConfigProvider $configProvider,
        private readonly File $fileDriver,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getCriticalCss(string $handle): string
    {
        if (array_key_exists($handle, $this->cache)) {
            return $this->cache[$handle];
        }

        return $this->cache[$handle] = $this->load($handle);
    }

    private function load(string $handle): string
    {
        // Layout handles are [a-z0-9_]; reject anything else so the handle can
        // never escape the critical directory.
        if ($handle === '' || preg_match('/[^a-z0-9_]/', $handle)) {
            return '';
        }
        if ($this->configProvider->isHmrEnabled()) {
            return '';
        }

        try {
            $fileId = $this->configProvider->getViteGeneratedPath()
                . '/' . self::CRITICAL_DIR . '/' . $handle . '.css';
            $source = $this->assetRepository->createAsset($fileId)->getSourceFile();
            if (!$this->fileDriver->isExists($source)) {
                return '';
            }

            return (string)$this->fileDriver->fileGetContents($source);
        } catch (Throwable $e) {
            $this->logger->warning(
                'MageObsidian: could not read critical CSS for handle "' . $handle . '": ' . $e->getMessage()
            );

            return '';
        }
    }
}
