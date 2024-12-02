<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Framework\View\Asset\PreProcessor;

use MageObsidian\ModernFrontend\Service\ConfigManager;
use Magento\Framework\View\Asset\PreProcessor\Chain;
use Magento\Framework\View\Asset\PreProcessor\Pool;

readonly class PoolPlugin
{
    public function __construct(
        private ConfigManager $configManager
    ) {
    }

    const array FILE_EXCLUDE = [
        'css',
        'less',
        'scss'
    ];

    public function aroundProcess(
        Pool $subject,
        callable $proceed,
        Chain $chain
    ) {
        $type = $chain->getTargetContentType();
        $asset = $chain->getAsset();
        $context = $asset->getContext();
        if (in_array(
                $type,
                self::FILE_EXCLUDE
            ) && $this->configManager->isThemeEnabled($context->getThemePath())) {
            return;
        }
        $proceed($chain);
    }
}
