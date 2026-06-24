<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Plugin\View\Asset;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use Magento\Framework\View\Asset\ConfigInterface;
use Magento\Framework\View\DesignInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Disables Magento's HTML template minification while a MageObsidian
 * (Vite/Twig) theme is active.
 *
 * In production with neither `force_html_minification` nor SCD-on-demand set,
 * TemplateFile::getMinifiedTemplateInProduction returns the *path* to a
 * pre-generated `.min` template without creating it; that file is never
 * produced for the Obsidian pipeline, so the include fails with HTTP 500.
 * Minifying also buys nothing here (Varnish + gzip already collapse the
 * whitespace) and its regex pass can corrupt the inline JSON the runtime emits
 * (island `data-props`, the importmap, JSON-LD). Turning the flag off for the
 * request is the safe fix; admin and legacy themes keep minifying.
 */
class MinifyHtmlThemePlugin
{
    public function __construct(
        private readonly DesignInterface $design,
        private readonly ConfigManagerInterface $configManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function afterIsMinifyHtml(ConfigInterface $subject, bool $result): bool
    {
        if (!$result) {
            return false;
        }
        return $this->isObsidianTheme() ? false : $result;
    }

    /**
     * Whether the active design theme opted into the MageObsidian pipeline.
     *
     * Any failure degrades to "not Obsidian" so the native minify behaviour is
     * left in place rather than masked.
     *
     * @return bool
     */
    private function isObsidianTheme(): bool
    {
        try {
            $themeCode = (string)$this->design->getDesignTheme()->getCode();
            return $themeCode !== '' && $this->configManager->isThemeEnabled($themeCode);
        } catch (Throwable $e) {
            $this->logger->warning(
                'MageObsidian: could not resolve the active theme for minify_html; '
                . 'leaving native minification in place: ' . $e->getMessage()
            );
            return false;
        }
    }
}
