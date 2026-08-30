<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Theme;

use Magento\Framework\View\DesignInterface;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ObsidianThemeDetector
{
    public function __construct(
        private readonly DesignInterface $design,
        private readonly ConfigManagerInterface $configManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Whether the active design theme opted into the MageObsidian pipeline.
     *
     * Any failure degrades to "not Obsidian" so the native behaviour is
     * left in place rather than masked.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        try {
            $themeCode = (string)$this->design->getDesignTheme()->getCode();
            return $themeCode !== '' && $this->configManager->isThemeEnabled($themeCode);
        } catch (Throwable $e) {
            $this->logger->warning(
                'MageObsidian: could not resolve the active theme; '
                . 'leaving native behaviour in place: ' . $e->getMessage()
            );
            return false;
        }
    }
}
