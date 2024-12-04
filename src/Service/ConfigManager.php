<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Service;

use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;
use Magento\Framework\App\DeploymentConfig\Writer\FormatterInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use RuntimeException;
use Magento\Framework\Module\ModuleList as MagentoModuleList;

class ConfigManager
{
    public const string CONFIG_FILE = 'app/etc/mage_obsidian_frontend_modules';
    public const string JSON_EXTENSION = '.json';
    public const string PHP_EXTENSION = '.php';
    public array $CONFIG_PATHS;
    private array $configData = [];

    public function __construct(
        private readonly ModuleList $moduleList,
        private readonly ThemeList $themeList,
        private readonly MagentoModuleList $magentoModuleList,
        private readonly DirectoryList $directoryList,
        private readonly DriverInterface $filesystemDriver,
        private readonly FormatterInterface $formatter,
        private readonly State $state
    ) {
        $this->CONFIG_PATHS = [
            'php' => $this->directoryList->getPath(DirectoryList::ROOT) . '/' . self::CONFIG_FILE . self::PHP_EXTENSION,
            'json' => $this->directoryList->getPath(
                    DirectoryList::ROOT
                ) . '/' . self::CONFIG_FILE . self::JSON_EXTENSION
        ];
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function generate(): array
    {
        $enabledModules = $this->moduleList->getAllEnabled();
        $enabledThemes = $this->themeList->getAllEnabled();

        $configModules = array_map(
            fn($module) => ['src' => $module['path']],
            $enabledModules
        );

        $configThemes = array_map(
            fn($theme) => [
                'src' => $theme['path'],
                'parent' => $theme['parent_code']
            ],
            $enabledThemes
        );

        $configData = [
            'modules' => $configModules,
            'themes' => $configThemes
        ];
        $this->writeFile(
            $this->getConfigFilePath()['php'],
            $this->formatter->format($configData)
        );
        $configData = [
            ...$configData,
            'allModules' => $this->magentoModuleList->getNames(),
            'VUE_COMPONENTS_PATH' => ConfigInterface::VUE_COMPONENTS_PATH,
            'JS_PATH' => ConfigInterface::JS_PATH,
            'FOLDERS_TO_WATCH' => ConfigInterface::FOLDERS_TO_WATCH,
            'ALLOWED_EXTENSIONS' => ConfigInterface::ALLOWED_EXTENSIONS,
            'MODULE_CSS_EXTEND_FILE' => ConfigInterface::MODULE_CSS_EXTEND_FILE,
            'MODULE_CONFIG_FILE' => ConfigInterface::MODULE_CONFIG_FILE,
            'THEME_CONFIG_FILE' => ConfigInterface::THEME_CONFIG_FILE,
            'THEME_CSS_SOURCE_FILE' => ConfigInterface::THEME_CSS_SOURCE_FILE,
            'LIB_PATH' => ConfigInterface::LIB_PATH
        ];
        $this->writeFile(
            $this->getConfigFilePath()['json'],
            json_encode(
                $configData,
                JSON_PRETTY_PRINT
            )
        );
        $this->configData = $configData;
        return $this->configData;
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function isModuleEnabled(string $moduleName): bool
    {
        $this->get();
        return isset($this->configData['modules'][$moduleName]);
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function isThemeEnabled(string $themeName): bool
    {
        $this->get();
        return isset($this->configData['themes'][$themeName]);
    }

    /**
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function get(): array
    {
        if (!empty($this->configData)) {
            return $this->configData;
        }
        $missingFile = !$this->hasConfig();

        if ($missingFile && $this->state->getMode() === State::MODE_PRODUCTION) {
            throw new RuntimeException(
                "Missing configuration file, please run the command 'mage-obsidian:frontend:config --generate.'"
            );
        } elseif ($missingFile) {
            return $this->generate();
        }
        $this->configData = require_once $this->getConfigFilePath()['php'];
        return $this->configData;
    }

    public function getConfigFilePath(): array
    {
        return $this->CONFIG_PATHS;
    }

    /**
     * @throws FileSystemException
     */
    public function hasConfig(): bool
    {
        $configFilePaths = $this->getConfigFilePath();

        return $this->filesystemDriver->isExists($configFilePaths['php']) && $this->filesystemDriver->isExists(
                $configFilePaths['json']
            );
    }

    /**
     *
     * @param string $filePath
     * @param string $data
     *
     * @throws RuntimeException
     */
    private function writeFile(string $filePath, string $data): void
    {
        try {
            $this->filesystemDriver->filePutContents(
                $filePath,
                $data
            );
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to write file: $filePath. Error: " . $e->getMessage());
        }
    }
}
