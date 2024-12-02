<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Package;

use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;
use MageObsidian\ModernFrontend\Service\ConfigManager;
use Magento\Deploy\Package\Package;
use Magento\Deploy\Package\PackageFile;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

readonly class PackageFilePlugin
{
    public function __construct(
        private ConfigManager $configManager
    ) {
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function aroundSetPackage(
        PackageFile $file,
        callable $proceed,
        Package $package
    ) {
        if ($this->canAddFile(
            $file,
            $package
        )) {
            return $proceed($package);
        }
        return null;
    }

    /**
     * Determine if the file can be added to the package.
     *
     * @throws LocalizedException
     * @throws FileSystemException
     */
    private function canAddFile(PackageFile $file, Package $package): bool
    {
        $theme = $package->getTheme();

        // Skip processing if the theme is disabled
        if (!$this->configManager->isThemeEnabled($theme)) {
            return true;
        }

        return $this->isFileEligibleForProcessing($file);
    }

    /**
     * Check if the file is eligible for processing.
     */
    private function isFileEligibleForProcessing(PackageFile $file): bool
    {
        $area = $file->getArea();
        if (empty($area)) {
            return false;
        }

        $module = $file->getModule() ?? '';
        $theme = $file->getTheme() ?? '';

        $hasTheme = !empty($theme);
        $hasModule = !empty($module);

        // Validate theme or module-specific conditions
        if (!$hasTheme && !$hasModule) {
            return false;
        }

        if ($hasTheme && !$this->configManager->isThemeEnabled($theme)) {
            return false;
        }

        if (!$hasTheme && !$this->configManager->isModuleEnabled($module)) {
            return false;
        }

        return $this->filterSourceFiles($file);
    }

    /**
     * Filter source files based on predefined patterns.
     */
    private function filterSourceFiles(PackageFile $file): bool
    {
        $deployedFileName = $file->getSourcePath();

        $patterns = $this->getPatterns($file);

        // Combine patterns into a single regex
        $regex = '#(' . implode(
                '|',
                $patterns
            ) . ')#';

        // Check for a match
        return !preg_match(
            $regex,
            $deployedFileName
        ); // Return true if no match is found
    }

    /**
     * Generate file filtering patterns based on the file context.
     */
    private function getPatterns(PackageFile $file): array
    {
        $basePaths = ['view/frontend/web/', 'view/base/web/'];
        $module = $file->getModule();
        $theme = $file->getTheme();

        $patterns = [];

        if (!$theme) {
            foreach ($basePaths as $path) {
                $patterns[] = preg_quote(
                        $path,
                        '#'
                    ) . 'module\.config\..*';
                $patterns[] = preg_quote(
                        $path,
                        '#'
                    ) . 'css/.*\.css';
                $patterns[] = preg_quote(
                        $path,
                        '#'
                    ) . ConfigInterface::VUE_COMPONENTS_PATH . '/.*';
                $patterns[] = preg_quote(
                        $path,
                        '#'
                    ) . ConfigInterface::JS_PATH . '/.*';
            }
        } else {
            $patterns[] = preg_quote(
                    $module,
                    '#'
                ) . '/web/module\.config\..*';
            $patterns[] = preg_quote(
                    $module,
                    '#'
                ) . '/web/css/.*\.css';
            $patterns[] = preg_quote(
                    $module,
                    '#'
                ) . '/web/' . ConfigInterface::VUE_COMPONENTS_PATH . '/.*';
            $patterns[] = preg_quote(
                    $module,
                    '#'
                ) . '/web/' . ConfigInterface::JS_PATH . '/.*';
            $patterns[] = preg_quote(
                    $theme,
                    '#'
                ) . '/web/theme\.config\.cjs';
            $patterns[] = preg_quote(
                    $theme,
                    '#'
                ) . '/web/css/.*\.css';
        }

        return $patterns;
    }
}
