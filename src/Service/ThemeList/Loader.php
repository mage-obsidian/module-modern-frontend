<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Service\ThemeList;

use Generator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Xml\Parser;
use Magento\Framework\Config\Dom;
use Magento\Framework\Config\ValidationStateInterface;
use Magento\Theme\Model\ResourceModel\Theme\Collection;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory;

class Loader extends \MageObsidian\ModernFrontend\Service\ModuleList\Loader
{
    const string XML_SCHEMA_PATH = '/etc/xsd/mage_obsidian_theme_compatibility.xsd';

    public function __construct(
        ComponentRegistrarInterface $moduleRegistry,
        ModuleList $moduleList,
        DriverInterface $filesystemDriver,
        Parser $parser,
        ValidationStateInterface $validationState,
        protected readonly CollectionFactory $themeCollection,
        protected readonly DirectoryList $directoryList,
    ) {
        parent::__construct(
            $moduleRegistry,
            $moduleList,
            $filesystemDriver,
            $parser,
            $validationState
        );
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function load(): array
    {
        $result = [];

        $schemaPath = $this->getSchemaPath();
        foreach ($this->getThemeConfigs() as list($themeCode, $parentThemeCode, $filePath, $contents)) {
            try {
                new Dom(
                                $contents,
                                $this->validationState,
                    schemaFile: $schemaPath
                );
                $data = $this->parser->loadXML($contents)
                                     ->xmlToArray();
                $data = $data['config']['_value'];
                $result[$themeCode] = [
                    'code' => $themeCode,
                    'parent_code' => $parentThemeCode,
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

    private function getThemeCollection(): Collection
    {
        $collection = $this->themeCollection->create();
        $collection->addFieldToSelect([
                                          'code',
                                          'theme_path'
                                      ])
                   ->filterPhysicalThemes()
                   ->getSelect()
                   ->joinLeft(
                       $collection->getTable('theme'),
                       'main_table.parent_id = theme.theme_id',
                       ['parent_code' => 'code']
                   );
        return $collection;
    }

    /**
     * Returns theme config data and a path to the mage-obsidian_compatibility.xml file.
     *
     * @return Generator
     */
    private function getThemeConfigs(): Generator
    {
        /**
         * @var $themes \Magento\Theme\Model\Theme[]
         */
        $themes = $this->getThemeCollection();
        foreach ($themes as $theme) {
            $rootPath = $this->moduleRegistry->getPath(
                ComponentRegistrar::THEME,
                "frontend/{$theme->getCode()}"
            );
            $filePath = $rootPath . self::XML_FILE_PATH;
            if (!$this->filesystemDriver->isExists($filePath)) {
                continue;
            }
            yield [
                $theme->getCode(),
                $theme->getParentCode(),
                $rootPath,
                $this->filesystemDriver->fileGetContents($filePath)
            ];
        }
    }
}


