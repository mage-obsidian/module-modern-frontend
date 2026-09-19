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
use Magento\Framework\Config\File\ConfigFilePool;
use Psr\Log\LoggerInterface;
use Throwable;

class WriterPlugin
{
    public function __construct(
        private readonly ConfigManagerInterface $configManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function afterSaveConfig(Writer $subject, mixed $result, array $data): mixed
    {
        if (!isset($data[ConfigFilePool::APP_CONFIG]['modules'])) {
            return $result;
        }

        try {
            $this->configManager->generate();
        } catch (Throwable $e) {
            $this->logger->warning(
                'MageObsidian: the frontend contract was not regenerated after the module list changed ('
                . $e->getMessage()
                . '). Run bin/magento mage-obsidian:frontend:config --generate.'
            );
        }

        return $result;
    }
}
