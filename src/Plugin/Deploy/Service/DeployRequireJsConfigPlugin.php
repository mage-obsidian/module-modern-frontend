<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Service;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use MageObsidian\ModernFrontend\Service\ConfigManager;
use Magento\Framework\App\Area;

class DeployRequireJsConfigPlugin
{
    /**
     * @param ConfigManager $configManager
     */
    public function __construct(
        private readonly ConfigManager $configManager
    ) {
    }

    /**
     * aroundDeploy
     *
     * @param $subject
     * @param callable $proceed
     * @param $areaCode
     * @param $themePath
     * @param $localeCode
     *
     * @return ?bool
     * @throws FileSystemException
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundDeploy(
        $subject,
        callable $proceed,
        $areaCode,
        $themePath,
        $localeCode
    ): ?bool {
        if ($areaCode !== Area::AREA_FRONTEND || !$this->configManager->isThemeEnabled($themePath)) {
            return $proceed(
                $areaCode,
                $themePath,
                $localeCode
            );
        }
        return true;
    }
}
