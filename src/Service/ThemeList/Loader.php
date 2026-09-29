<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\ThemeList;

use DOMDocument;
use Generator;
use Magento\Framework\App\Area;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Config\Dom;
use Magento\Framework\Config\Dom\ValidationException;
use Magento\Framework\Config\ThemeFactory;
use Magento\Framework\Config\ValidationStateInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\View\Design\Theme\ThemePackageList;
use Magento\Framework\Xml\Parser;
use Magento\Framework\Xml\ParserFactory;
use Psr\Log\LoggerInterface;

class Loader extends \MageObsidian\ModernFrontend\Service\ModuleList\Loader
{
    public const string XML_SCHEMA_PATH = '/etc/xsd/mage_obsidian_theme_compatibility.xsd';
    private const string THEME_XML = '/theme.xml';

    public function __construct(
        ComponentRegistrarInterface $moduleRegistry,
        ModuleList $moduleList,
        DriverInterface $filesystemDriver,
        Parser $parser,
        ValidationStateInterface $validationState,
        ParserFactory $parserFactory,
        LoggerInterface $logger,
        protected readonly ThemePackageList $themePackageList,
        protected readonly ThemeFactory $themeConfigFactory,
    ) {
        parent::__construct($moduleRegistry, $moduleList, $filesystemDriver, $parser, $validationState, $parserFactory, $logger);
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
                new Dom($contents, $this->validationState, schemaFile: $schemaPath);
                $data = $this->parser->loadXML($contents)
                                     ->xmlToArray();
                $data = $data['config']['_value'];
                $result[$themeCode] = [
                    'code' => $themeCode,
                    'parent_code' => $parentThemeCode,
                    'data' => $data,
                    'path' => $filePath,
                ];
            } catch (ValidationException $e) {
                $this->logger->warning(sprintf(
                    'MageObsidian: skipping theme "%s" — invalid %s at %s: %s',
                    $themeCode,
                    self::XML_FILE_NAME,
                    $filePath,
                    $e->getMessage()
                ));
            }
        }

        return $result;
    }

    private function getThemeConfigs(): Generator
    {
        foreach ($this->themePackageList->getThemes() as $package) {
            if ($package->getArea() !== Area::AREA_FRONTEND) {
                continue;
            }
            $rootPath = $package->getPath();
            $filePath = $rootPath . self::XML_FILE_PATH;
            if (!$this->filesystemDriver->isExists($filePath)) {
                continue;
            }
            $themeCode = $package->getVendor() . '/' . $package->getName();
            yield [
                $themeCode,
                $this->parentOf($themeCode, $rootPath),
                $rootPath,
                $this->filesystemDriver->fileGetContents($filePath),
            ];
        }
    }

    private function parentOf(string $themeCode, string $rootPath): ?string
    {
        $themeXml = $rootPath . self::THEME_XML;
        $contents = $this->filesystemDriver->isExists($themeXml)
            ? (string)$this->filesystemDriver->fileGetContents($themeXml)
            : '';
        if (!self::isThemeDocument($contents)) {
            throw new LocalizedException(__(
                'MageObsidian: the theme "%1" has no readable theme.xml at %2.',
                $themeCode,
                $themeXml
            ));
        }
        $parent = $this->themeConfigFactory->create(['configContent' => $contents])->getParentTheme();

        return $parent === null ? null : implode('/', $parent);
    }

    private static function isThemeDocument(string $contents): bool
    {
        if (trim($contents) === '') {
            return false;
        }
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->loadXML($contents);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded && $document->getElementsByTagName('theme')->length > 0;
    }
}
