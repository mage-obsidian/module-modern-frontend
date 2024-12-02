<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Package\Processor;

use MageObsidian\ModernFrontend\Service\ConfigManager;
use Magento\Deploy\Package\Package;
use Magento\Deploy\Package\Processor\ProcessorInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

readonly class FilterFilesThemePlugin
{
    public function __construct(
        private ConfigManager $configManager
    ) {
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function aroundProcess(
        ProcessorInterface $subject,
        callable $proceed,
        Package $package,
        array $options
    ) {
        if (!$this->configManager->isThemeEnabled($package->getTheme())) {
            return $proceed(
                $package,
                $options
            );
        }
        return true;
    }
}
