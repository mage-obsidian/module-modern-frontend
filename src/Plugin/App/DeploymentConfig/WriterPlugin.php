<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Plugin\App\DeploymentConfig;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use Magento\Framework\App\DeploymentConfig\Writer;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

class WriterPlugin
{
    /**
     * WriterPlugin constructor.
     *
     * @param ConfigManagerInterface $configManager
     */
    public function __construct(
        private readonly ConfigManagerInterface $configManager
    ) {
    }

    /**
     * Regenerate the frontend contract whenever the deployment config is saved.
     *
     * Writer::saveConfig() is a void method, so the intercepted result is null;
     * the parameter must stay untyped (not bool) and the value is propagated
     * unchanged. Typing it `bool` made every saveConfig() call fatal — including
     * deploy:mode:set, which writes the mode through this writer.
     *
     * @param Writer $subject
     * @param mixed $result
     *
     * @return mixed
     * @throws FileSystemException
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSaveConfig(
        Writer $subject,
        mixed $result
    ): mixed {
        $this->configManager->generate();
        return $result;
    }
}
