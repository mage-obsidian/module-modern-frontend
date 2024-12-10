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
    /**
     * @var array|null
     */
    private ?array $enabled = null;
    /**
     * @var array|null
     */
    private ?array $all = null;

    /**
     * ModuleList constructor.
     *
     * @param Loader $loader
     */
    public function __construct(
        private readonly Loader $loader,
    ) {
    }

    /**
     * Get all enabled modules.
     *
     * @return array
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
     * Get all modules.
     *
     * @return array
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
