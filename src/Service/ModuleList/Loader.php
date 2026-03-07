<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\ModuleList;

use Generator;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Xml\Parser;
use Magento\Framework\Xml\ParserFactory;
use Magento\Framework\Config\Dom;
use Magento\Framework\Config\Dom\ValidationException;
use Magento\Framework\Config\ValidationStateInterface;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;

class Loader
{
    public const string XML_FILE_NAME = 'mage_obsidian_compatibility.xml';
    public const string XML_FILE_PATH = '/etc/' . self::XML_FILE_NAME;
    public const string XML_SCHEMA_PATH = '/etc/xsd/mage_obsidian_compatibility.xsd';

    /**
     * Loader constructor.
     *
     * @param ComponentRegistrarInterface $moduleRegistry
     * @param ModuleList $moduleList
     * @param DriverInterface $filesystemDriver
     * @param Parser $parser
     * @param ValidationStateInterface $validationState
     * @param ParserFactory $parserFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected ComponentRegistrarInterface $moduleRegistry,
        protected ModuleList $moduleList,
        protected DriverInterface $filesystemDriver,
        protected Parser $parser,
        protected ValidationStateInterface $validationState,
        protected ParserFactory $parserFactory,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function load(): array
    {
        $result = [];

        $schemaPath = $this->getSchemaPath();
        foreach ($this->getModuleConfigs() as list($moduleName, $filePath, $contents)) {
            // A single malformed or invalid descriptor must not take down the
            // whole contract: log it and skip that module so the rest still
            // load. A broken XSD (ValidationSchemaException) is our bug and is
            // left to propagate loudly.
            try {
                new Dom($contents, $this->validationState, schemaFile: $schemaPath);
                $data = $this->parser->loadXML($contents)
                                     ->xmlToArray();
                $data = $data['config']['_value'];
                $result[$moduleName] = [
                    'name' => $moduleName,
                    'data' => $data,
                    'path' => $filePath,
                ];
            } catch (ValidationException $e) {
                $this->logger->warning(sprintf(
                    'MageObsidian: skipping module "%s" — invalid %s at %s: %s',
                    $moduleName,
                    self::XML_FILE_NAME,
                    $filePath,
                    $e->getMessage()
                ));
            }
        }

        return $result;
    }

    /**
     * Returns module config data and a path to the mage-obsidian_compatibility.xml file.
     *
     * @return Generator
     * @throws FileSystemException
     */
    private function getModuleConfigs(): Generator
    {
        $moduleEnables = $this->moduleList->getNames();
        foreach ($moduleEnables as $moduleName) {
            $modulePath = $this->moduleRegistry->getPath(ComponentRegistrar::MODULE, $moduleName);
            $filePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, "$modulePath" . self::XML_FILE_PATH);
            if (!$this->filesystemDriver->isExists($filePath)) {
                continue;
            }
            yield [$moduleName, $modulePath, $this->filesystemDriver->fileGetContents($filePath)];
        }
    }

    /**
     * Get the XSD schema path for the module.
     *
     * @return string
     * @throws FileSystemException
     */
    protected function getSchemaPath(): string
    {
        $modulePath = $this->moduleRegistry->getPath(ComponentRegistrar::MODULE, 'MageObsidian_ModernFrontend');
        $schemaPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, "$modulePath" . $this::XML_SCHEMA_PATH);

        if (!$this->filesystemDriver->isExists($schemaPath)) {
            throw new FileSystemException(new Phrase('Schema file not found for module MageObsidian_ModernFrontend'));
        }

        return $schemaPath;
    }
}
