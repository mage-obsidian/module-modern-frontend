<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Framework\Module;

use MageObsidian\ModernFrontend\Service\ConfigManager;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager as MagentoManager;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\Output;
use Magento\Framework\View\DesignInterface;

class Manager extends MagentoManager
{
    private ?bool $isEnabled = null;

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function __construct(
        Output\ConfigInterface $outputConfig,
        ModuleListInterface $moduleList,
        private readonly DesignInterface $design,
        private readonly ConfigManager $configManager,
        array $outputConfigPaths = []
    ) {
        parent::__construct(
            $outputConfig,
            $moduleList,
            $outputConfigPaths
        );
        $this->load();
    }

    /**
     * @throws FileSystemException
     * @throws LocalizedException
     */
    private function load(): void
    {
        $themeCode = $this->design->getDesignTheme()
                                  ->getCode();
        if (!empty($themeCode)) {
            $this->isEnabled = $this->configManager->isThemeEnabled($themeCode);
        }
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    private function isThemeEnabled(): ?bool
    {
        if ($this->isEnabled !== null) {
            return $this->isEnabled;
        }
        $this->load();
        return $this->isEnabled;
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function isOutputEnabled(
        $moduleName
    ): bool {
        $result = parent::isOutputEnabled($moduleName);
        if (!$this->isThemeEnabled()) {
            return $result;
        }
        return $result && $this->configManager->isModuleEnabled($moduleName);
    }
}
