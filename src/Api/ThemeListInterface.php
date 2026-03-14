<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Api;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

/**
 * Reads the MageObsidian-compatible themes declared via
 * etc/mage_obsidian_compatibility.xml.
 *
 * @api
 */
interface ThemeListInterface
{
    /**
     * Themes whose compatibility feature flag is enabled, keyed by theme code.
     *
     * @return array<string, array>
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function getAllEnabled(): array;

    /**
     * Every theme that ships a compatibility descriptor, keyed by theme code.
     *
     * @return array<string, array>
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function getAll(): array;
}
