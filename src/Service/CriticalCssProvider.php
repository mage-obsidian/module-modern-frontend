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
use Magento\Framework\View\Asset\File\NotFoundException;
use Magento\Framework\View\Asset\Repository;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reads the per-handle critical CSS from the theme's `critical/<handle>.css`,
 * falling back to `<generated>/critical/<handle>.css` from older CLI releases
 * (see the `mage-obsidian:frontend:critical-css` command). The content — not a
 * URL — is returned so the head can inline it.
 *
 * Read from the theme source like the Vite manifest (works in any deploy mode),
 * cached per process, skipped under HMR, and degraded to an empty string on any
 * failure so a missing/unreadable file just falls back to the render-blocking
 * stylesheet rather than breaking the page.
 */
class CriticalCssProvider
{
    private const CRITICAL_DIR = 'critical';
    private const string FONT_URL = '#url\(\s*([\'"]?)\.\./([A-Za-z0-9._-]+\.woff2)\1\s*\)#';

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
            foreach ($this->candidates($handle) as $fileId) {
                $css = $this->read($fileId);
                if ($css !== null) {
                    return $css;
                }
            }

            return '';
        } catch (Throwable $e) {
            $this->logger->warning(
                'MageObsidian: could not read critical CSS for handle "' . $handle . '": ' . $e->getMessage()
            );

            return '';
        }
    }

    private function candidates(string $handle): array
    {
        $file = self::CRITICAL_DIR . '/' . $handle . '.css';

        return [$file, $this->configProvider->getViteGeneratedPath() . '/' . $file];
    }

    private function read(string $fileId): ?string
    {
        try {
            $source = $this->assetRepository->createAsset($fileId)->getSourceFile();
        } catch (NotFoundException) {
            return null;
        }

        return $this->fileDriver->isExists($source)
            ? $this->withServedFontUrls((string)$this->fileDriver->fileGetContents($source))
            : null;
    }

    private function withServedFontUrls(string $css): string
    {
        return (string)preg_replace_callback(
            self::FONT_URL,
            fn (array $match): string => 'url(' . $this->fontUrl($match[2]) . ')',
            $css
        );
    }

    private function fontUrl(string $name): string
    {
        $url = (string)preg_replace('/\s+/', '', $this->assetRepository->getUrlWithParams(
            $this->configProvider->getViteGeneratedPath() . '/' . $name,
            ['_secure' => true]
        ));

        return (string)preg_replace('#^https?://[^/]+#i', '', $url);
    }
}
