<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Service;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use MageObsidian\ModernFrontend\Service\ModuleList\Loader;

class ModuleList
{
    private ?array $enabled = null;
    private ?array $all = null;

    public function __construct(
        private readonly Loader $loader,
    ) {
    }

    /**
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function getAllEnabled(): array
    {
        if (null === $this->enabled) {
            $all = $this->getAll();
            if (empty($all)) {
                return [];
            }
            $this->enabled = [];
            foreach ($all as $moduleName => $moduleConfig) {
                $isEnabled = (boolean)$moduleConfig['data']['features']['compatibility'];
                if ($isEnabled) {
                    $this->enabled[$moduleName] = $moduleConfig;
                }
            }
        }
        return $this->enabled;
    }

    /**
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function getAll(): array
    {
        if (null === $this->all) {
            $this->all = $this->loader->load();
        }
        return $this->all;
    }
}
