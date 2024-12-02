<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Service;

use MageObsidian\ModernFrontend\Service\ConfigManager\Proxy;
use Magento\Framework\App\Area;

class DeployRequireJsConfigPlugin
{
    public function __construct(
        private Proxy $configManager
    ) {
    }

    public function aroundDeploy(
        $subject,
        callable $proceed,
        $areaCode,
        $themePath,
        $localeCode
    ) {
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
