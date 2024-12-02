<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
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
use Magento\Framework\Config\ValidationStateInterface;

class Loader
{
    const XML_FILE_NAME = 'mage_obsidian_compatibility.xml';
    const XML_FILE_PATH = '/etc/' . self::XML_FILE_NAME;
    const XML_SCHEMA_PATH = '/etc/xsd/mage_obsidian_compatibility.xsd';
    protected ParserFactory $parserFactory;

    public function __construct(
        protected ComponentRegistrarInterface $moduleRegistry,
        protected ModuleList $moduleList,
        protected DriverInterface $filesystemDriver,
        protected Parser $parser,
        protected ValidationStateInterface $validationState
    ) {
        $this->parserFactory = ObjectManager::getInstance()
                                            ->get(ParserFactory::class);
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
            try {
                new Dom(
                                $contents,
                                $this->validationState,
                    schemaFile: $schemaPath
                );
                $data = $this->parser->loadXML($contents)
                                     ->xmlToArray();
                $data = $data['config']['_value'];
                $result[$moduleName] = [
                    'name' => $moduleName,
                    'data' => $data,
                    'path' => $filePath,
                ];
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    new \Magento\Framework\Phrase(
                        'Invalid Document in %1: %2',
                        [$filePath, $e->getMessage()]
                    ),
                    $e
                );
            }
        }

        return $result;
    }

    /**
     * Returns module config data and a path to the mage-obsidian_compatibility.xml file.
     *
     * @return Generator
     */
    private function getModuleConfigs()
    {
        $moduleEnables = $this->moduleList->getNames();
        foreach ($moduleEnables as $moduleName) {
            $modulePath = $this->moduleRegistry->getPath(
                ComponentRegistrar::MODULE,
                $moduleName
            );
            $filePath = str_replace(['\\', '/'],
                                    DIRECTORY_SEPARATOR,
                                    "$modulePath" . self::XML_FILE_PATH);
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
        $modulePath = $this->moduleRegistry->getPath(
            ComponentRegistrar::MODULE,
            'MageObsidian_ModernFrontend'
        );
        $schemaPath = str_replace(['\\', '/'],
                                  DIRECTORY_SEPARATOR,
                                  "$modulePath" . $this::XML_SCHEMA_PATH);

        if (!$this->filesystemDriver->isExists($schemaPath)) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('Schema file not found for module MageObsidian_ModernFrontend')
            );
        }

        return $schemaPath;
    }
}


