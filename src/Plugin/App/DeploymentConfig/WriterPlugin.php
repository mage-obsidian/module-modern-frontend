<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Plugin\App\DeploymentConfig;

use MageObsidian\ModernFrontend\Service\ConfigManager;
use Magento\Framework\App\DeploymentConfig\Writer;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

class WriterPlugin
{
    /**
     * WriterPlugin constructor.
     *
     * @param ConfigManager $configManager
     */
    public function __construct(
        private readonly ConfigManager $configManager
    ) {
    }

    /**
     * After save config method.
     *
     * @param Writer $subject
     * @param bool $result
     *
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function afterSaveConfig(
        Writer $subject,
        $result
    ) {
        $this->configManager->generate();
        return $result;
    }
}
