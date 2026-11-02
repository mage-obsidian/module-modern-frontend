<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Theme;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Design\Theme\FlyweightFactory;
use Magento\Framework\View\DesignInterface;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ObsidianThemeDetector
{
    public function __construct(
        private readonly DesignInterface $design,
        private readonly ConfigManagerInterface $configManager,
        private readonly LoggerInterface $logger,
        private readonly State $appState,
        private readonly FlyweightFactory $themes
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
            $themeCode = $this->themeCode();
            return $themeCode !== '' && $this->configManager->isThemeEnabled($themeCode);
        } catch (Throwable $e) {
            $this->logger->warning(
                'MageObsidian: could not resolve the active theme; '
                . 'leaving native behaviour in place: ' . $e->getMessage()
            );
            return false;
        }
    }

    private function themeCode(): string
    {
        $themeCode = (string)$this->design->getDesignTheme()->getCode();
        if ($themeCode !== '' || !$this->servesTheStorefront()) {
            return $themeCode;
        }

        $storeTheme = $this->design->getConfigurationDesignTheme(Area::AREA_FRONTEND);

        return $storeTheme ? (string)$this->themes->create($storeTheme, Area::AREA_FRONTEND)->getCode() : '';
    }

    private function servesTheStorefront(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND;
        } catch (LocalizedException) {
            return false;
        }
    }
}
