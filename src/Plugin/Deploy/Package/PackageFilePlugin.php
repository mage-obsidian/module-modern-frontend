<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Package;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;
use Magento\Deploy\Package\Package;
use Magento\Deploy\Package\PackageFile;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

readonly class PackageFilePlugin
{
    private const EXCLUDED_MODULE_CONFIG = 'module\.config\..*';
    private const EXCLUDED_CSS = 'css/.*\.css';
    private const EXCLUDED_THEME_CONFIG = 'theme\.config\.cjs';
    private const EXCLUDED_NODE_MODULES = 'node_modules/.*';

    public function __construct(
        private ConfigManagerInterface $configManager
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
        $module = $file->getModule() ?? '';
        $isObsidianModuleFile = $module !== '' && $this->configManager->isModuleEnabled($module);

        // Legacy theme packages (blank/luma) deploy normally — EXCEPT for the web
        // assets of an enabled MageObsidian module. Those are Vite-only ESM/Vue/CSS
        // and must be filtered out of the legacy Less/RequireJS pipeline regardless
        // of the theme being deployed; otherwise the pipeline tries to read ESM it
        // cannot parse. Discriminate by module, not by theme.
        if (!$isObsidianModuleFile && !$this->configManager->isThemeEnabled($package->getTheme())) {
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
                $quotedPath = preg_quote($path, '#');
                $patterns[] = $quotedPath . self::EXCLUDED_MODULE_CONFIG;
                $patterns[] = $quotedPath . self::EXCLUDED_CSS;
                $patterns[] = $quotedPath . ConfigInterface::VUE_COMPONENTS_PATH . '/.*';
                $patterns[] = $quotedPath . ConfigInterface::JS_PATH . '/.*';
                $patterns[] = $quotedPath . self::EXCLUDED_NODE_MODULES;
            }
        } else {
            $quotedModule = preg_quote($module, '#');
            $patterns[] = $quotedModule . '/web/' . self::EXCLUDED_MODULE_CONFIG;
            $patterns[] = $quotedModule . '/web/' . self::EXCLUDED_CSS;
            $patterns[] = $quotedModule . '/web/' . ConfigInterface::VUE_COMPONENTS_PATH . '/.*';
            $patterns[] = $quotedModule . '/web/' . ConfigInterface::JS_PATH . '/.*';
            $patterns[] = $quotedModule . '/web/' . self::EXCLUDED_NODE_MODULES;

            $quotedTheme = preg_quote($theme, '#');
            $patterns[] = $quotedTheme . '/web/' . self::EXCLUDED_THEME_CONFIG;
            $patterns[] = $quotedTheme . '/web/' . self::EXCLUDED_CSS;
            $patterns[] = $quotedTheme . '/web/' . self::EXCLUDED_NODE_MODULES;
        }

        return $patterns;
    }
}
