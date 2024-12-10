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
    /**
     * @var bool|null
     */
    private ?bool $isEnabled = null;

    /**
     * Manager constructor.
     *
     * @param Output\ConfigInterface $outputConfig
     * @param ModuleListInterface $moduleList
     * @param DesignInterface $design
     * @param ConfigManager $configManager
     * @param array $outputConfigPaths
     *
     * @return void
     *
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function __construct(
        Output\ConfigInterface $outputConfig,
        ModuleListInterface $moduleList,
        private readonly DesignInterface $design,
        private readonly ConfigManager $configManager,
        array $outputConfigPaths = []
    ) {
        parent::__construct($outputConfig, $moduleList, $outputConfigPaths);
        $this->load();
    }

    /**
     * Load the configuration of the current theme.
     *
     * @return void
     *
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
     * Check if the current theme is enabled.
     *
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
     * Check if the output is enabled for a module.
     *
     * @param string $moduleName
     *
     * @return bool
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
