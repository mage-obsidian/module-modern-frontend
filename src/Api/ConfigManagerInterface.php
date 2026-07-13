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
 * Generates and reads the PHP↔JS contract
 * (app/etc/mage_obsidian_frontend_modules.{php,json}) that bridges the PHP
 * modules and the Vite build engine.
 *
 * @api
 */
interface ConfigManagerInterface
{
    /**
     * Regenerate both contract files from the current module/theme state and
     * return the JSON contract payload.
     *
     * @return array
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function generate(): array;

    /**
     * Return the contract payload, generating it on the fly in non-production
     * modes when it is missing.
     *
     * @return array
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function get(): array;

    /**
     * Whether the named module is in the compatible-module contract.
     *
     * @param string $moduleName
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function isModuleEnabled(string $moduleName): bool;

    /**
     * Whether the named theme is in the compatible-theme contract.
     *
     * @param string $themeName
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function isThemeEnabled(string $themeName): bool;

    /**
     * Whether the named module opts into every theme (its layout is collected
     * even under non-Obsidian themes), via the <universal> compatibility flag.
     *
     * @param string $moduleName
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function isModuleUniversal(string $moduleName): bool;

    /**
     * Whether both contract files exist on disk.
     *
     * @return bool
     * @throws FileSystemException
     */
    public function hasConfig(): bool;

    /**
     * Absolute paths of the generated contract files, keyed by 'php' and 'json'.
     *
     * @return array{php:string, json:string}
     */
    public function getConfigFilePath(): array;

    /**
     * Diff the on-disk contract against the contract recomputed from the current
     * enabled modules/themes, per section.
     *
     * Detects drift the deployment-config seam cannot catch (e.g. editing a
     * module's compatibility flag without re-toggling it).
     *
     * @return array{
     *     modules: array{added: string[], removed: string[], changed: string[]},
     *     themes: array{added: string[], removed: string[], changed: string[]}
     * }
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function detectDrift(): array;
}
