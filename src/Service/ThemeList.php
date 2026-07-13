<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service;

use MageObsidian\ModernFrontend\Api\ThemeListInterface;
use MageObsidian\ModernFrontend\Service\ThemeList\Loader;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

class ThemeList implements ThemeListInterface
{
    /**
     * @var array|null
     */
    private ?array $enabled = null;
    /**
     * @var array|null
     */
    private ?array $all = null;

    /**
     * ThemeList constructor.
     *
     * @param Loader $loader
     */
    public function __construct(
        private readonly Loader $loader,
    ) {
    }

    /**
     * Get all enabled themes.
     *
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function getAllEnabled(): array
    {
        if (null === $this->enabled) {
            $all = $this->getAll();
            if (empty($all)) {
                return [];
            }
            $this->enabled = [];
            foreach ($all as $themeName => $themeConfig) {
                $isEnabled = filter_var($themeConfig['data']['features']['compatibility'], FILTER_VALIDATE_BOOLEAN);
                if ($isEnabled) {
                    $this->enabled[$themeName] = $themeConfig;
                }
            }
        }
        return $this->enabled;
    }

    /**
     * Get all themes.
     *
     * @return array
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function getAll(): array
    {
        if (null === $this->all) {
            $this->all = $this->loader->load();
        }
        return $this->all;
    }
}
