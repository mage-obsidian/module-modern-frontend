<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service;

use JsonSchema\Validator as JsonSchemaValidator;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;
use MageObsidian\ModernFrontend\Api\ModuleListInterface;
use MageObsidian\ModernFrontend\Api\ThemeListInterface;
use MageObsidian\ModernFrontend\Service\Contract\ContractDiff;
use Magento\Framework\App\DeploymentConfig\Writer\FormatterInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Magento\Framework\Module\ModuleList as MagentoModuleList;

class ConfigManager implements ConfigManagerInterface
{
    public const string CONFIG_FILE = 'app/etc/mage_obsidian_frontend_modules';
    public const string JSON_EXTENSION = '.json';
    public const string PHP_EXTENSION = '.php';
    private const string MODULE_NAME = 'MageObsidian_ModernFrontend';
    private const string SCHEMA_FILE = 'mage_obsidian_frontend_contract.schema.json';
    /**
     * @var array
     */
    public array $CONFIG_PATHS;
    /**
     * @var array
     */
    private array $configData = [];

    /**
     * ConfigManager constructor.
     *
     * @param ModuleListInterface $moduleList
     * @param ThemeListInterface $themeList
     * @param MagentoModuleList $magentoModuleList
     * @param DirectoryList $directoryList
     * @param DriverInterface $filesystemDriver
     * @param FormatterInterface $formatter
     * @param State $state
     * @param ModuleDirReader $moduleDirReader
     * @param LoggerInterface $logger
     *
     * @throws LocalizedException
     */
    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly ThemeListInterface $themeList,
        private readonly MagentoModuleList $magentoModuleList,
        private readonly DirectoryList $directoryList,
        private readonly DriverInterface $filesystemDriver,
        private readonly FormatterInterface $formatter,
        private readonly State $state,
        private readonly ModuleDirReader $moduleDirReader,
        private readonly LoggerInterface $logger
    ) {
        $this->CONFIG_PATHS = [
            'php' => $this->directoryList->getPath(DirectoryList::ROOT) . '/' . self::CONFIG_FILE . self::PHP_EXTENSION,
            'json' => $this->directoryList->getPath(DirectoryList::ROOT) . '/' . self::CONFIG_FILE . self::JSON_EXTENSION
        ];
    }

    /**
     * Generate the configuration file.
     *
     * @return array
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function generate(): array
    {
        $enabledModules = $this->moduleList->getAllEnabled();
        $enabledThemes = $this->themeList->getAllEnabled();

        $configModules = array_map(fn($module) => $this->buildModuleEntry($module), $enabledModules);

        $configThemes = array_map(fn($theme) => [
            'src' => $theme['path'],
            'parent' => $theme['parent_code']
        ], $enabledThemes);

        // schema_version + mode are emitted into BOTH files so either consumer
        // (PHP get(), JS configResolver) can detect drift. mode drives isDev()
        // on the JS side; without it the build defaults to dev.
        $baseConfig = [
            'schema_version' => ConfigInterface::SCHEMA_VERSION,
            'mode' => $this->state->getMode(),
            'modules' => $configModules,
            'themes' => $configThemes
        ];
        $this->writeFile($this->getConfigFilePath()['php'], $this->formatter->format($baseConfig));

        $jsonConfig = [
            ...$baseConfig,
            'allModules' => $this->magentoModuleList->getNames(),
            'VUE_COMPONENTS_PATH' => ConfigInterface::VUE_COMPONENTS_PATH,
            'JS_PATH' => ConfigInterface::JS_PATH,
            'FOLDERS_TO_WATCH' => ConfigInterface::FOLDERS_TO_WATCH,
            'ALLOWED_EXTENSIONS' => ConfigInterface::ALLOWED_EXTENSIONS,
            'MODULE_CSS_EXTEND_FILE' => ConfigInterface::MODULE_CSS_EXTEND_FILE,
            'MODULE_CONFIG_FILE' => ConfigInterface::MODULE_CONFIG_FILE,
            'THEME_CONFIG_FILE' => ConfigInterface::THEME_CONFIG_FILE,
            'THEME_CSS_SOURCE_FILE' => ConfigInterface::THEME_CSS_SOURCE_FILE,
            'THEME_FILES_PATH' => ConfigInterface::THEME_FILES_PATH,
            'LIB_PATH' => ConfigInterface::LIB_PATH
        ];

        // An empty section is array_map([]) === [], which json_encode serializes
        // as a JSON array, not an object — failing the schema and the JS object
        // access. Coerce to objects for the on-disk JSON only; the in-memory and
        // .php copies stay arrays so PHP offset access (isModuleEnabled, etc.)
        // keeps working. This is a real, reachable case (e.g. no compatible theme).
        $jsonForFile = $jsonConfig;
        if ($jsonForFile['modules'] === []) {
            $jsonForFile['modules'] = new \stdClass();
        }
        if ($jsonForFile['themes'] === []) {
            $jsonForFile['themes'] = new \stdClass();
        }

        $this->assertValidContract($jsonForFile);
        $this->writeFile($this->getConfigFilePath()['json'], json_encode($jsonForFile, JSON_PRETTY_PRINT));
        $this->configData = $jsonConfig;
        return $this->configData;
    }

    /**
     * Validate the generated contract against the JSON schema before writing it.
     *
     * Catches drift between this generator and the shape the JS engine expects
     * (the schema is the single source of truth for that shape).
     *
     * @param array $configData
     *
     * @return void
     * @throws LocalizedException
     * @throws FileSystemException
     */
    private function assertValidContract(array $configData): void
    {
        $schemaPath = $this->moduleDirReader->getModuleDir('etc', self::MODULE_NAME)
            . '/' . self::SCHEMA_FILE;
        $schema = json_decode($this->filesystemDriver->fileGetContents($schemaPath));

        // Round-trip through JSON so associative arrays become the stdClass
        // objects the validator expects for "type: object".
        $payload = json_decode(json_encode($configData));

        $validator = new JsonSchemaValidator();
        $validator->validate($payload, $schema);

        if ($validator->isValid()) {
            return;
        }

        $details = array_map(
            static fn(array $error): string => trim(sprintf('%s: %s', $error['property'], $error['message'])),
            $validator->getErrors()
        );
        $message = 'Generated MageObsidian frontend contract failed schema validation: '
            . implode('; ', $details);
        $this->logger->error($message);
        throw new LocalizedException(__($message));
    }

    /**
     * Check if a module is enabled.
     *
     * @param string $moduleName
     *
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function isModuleEnabled(string $moduleName): bool
    {
        $this->get();
        return isset($this->configData['modules'][$moduleName]);
    }

    /**
     * Check if a theme is enabled.
     *
     * @param string $themeName
     *
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function isThemeEnabled(string $themeName): bool
    {
        $this->get();
        return isset($this->configData['themes'][$themeName]);
    }

    /**
     * Whether the named module opts into every theme via the <universal> flag.
     *
     * @param string $moduleName
     *
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function isModuleUniversal(string $moduleName): bool
    {
        $this->get();
        return !empty($this->configData['modules'][$moduleName]['universal']);
    }

    /**
     * Build a contract entry for one enabled module. Shared by generate() and
     * detectDrift() so the on-disk shape and the expected shape never diverge
     * (a divergence would make detectDrift report phantom "changed" modules).
     *
     * @param array $module
     *
     * @return array
     */
    private function buildModuleEntry(array $module): array
    {
        $entry = ['src' => $module['path']];
        // The XML leaf arrives as the string 'false'/'true'; (bool)'false' is
        // true, so validate the boolean instead of casting.
        if (filter_var($module['data']['features']['universal'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $entry['universal'] = true;
        }
        return $entry;
    }

    /**
     * Diff the on-disk contract against the contract recomputed from the current
     * enabled modules/themes.
     *
     * @return array{
     *     modules: array{added: string[], removed: string[], changed: string[]},
     *     themes: array{added: string[], removed: string[], changed: string[]}
     * }
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function detectDrift(): array
    {
        $current = $this->get();

        $expectedModules = array_map(
            fn(array $module): array => $this->buildModuleEntry($module),
            $this->moduleList->getAllEnabled()
        );
        $expectedThemes = array_map(
            static fn(array $theme): array => ['src' => $theme['path'], 'parent' => $theme['parent_code']],
            $this->themeList->getAllEnabled()
        );

        return [
            'modules' => ContractDiff::section($current['modules'] ?? [], $expectedModules),
            'themes' => ContractDiff::section($current['themes'] ?? [], $expectedThemes),
        ];
    }

    /**
     * Get the configuration data.
     *
     * @return array
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
            throw new RuntimeException("Missing configuration file, please run the command 'mage-obsidian:frontend:config --generate.'");
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

        return $this->filesystemDriver->isExists($configFilePaths['php']) && $this->filesystemDriver->isExists($configFilePaths['json']);
    }

    /**
     * Write the contract file atomically (temp file + rename).
     *
     * A consumer reading the file mid-write would otherwise see a truncated
     * contract; rename on the same filesystem is atomic, so readers see either
     * the old file or the complete new one.
     *
     * @param string $filePath
     * @param string $data
     *
     * @return void
     * @throws FileSystemException
     */
    private function writeFile(string $filePath, string $data): void
    {
        $tmpPath = $filePath . '.tmp';
        try {
            $this->filesystemDriver->filePutContents($tmpPath, $data);
            $this->filesystemDriver->rename($tmpPath, $filePath);
        } catch (FileSystemException $e) {
            $this->logger->error(
                sprintf('Failed to write MageObsidian frontend contract %s: %s', $filePath, $e->getMessage())
            );
            if ($this->filesystemDriver->isExists($tmpPath)) {
                $this->filesystemDriver->deleteFile($tmpPath);
            }
            throw $e;
        }
    }
}
