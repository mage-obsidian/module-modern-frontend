<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Dev;

/**
 * Resolves which theme a dev command should target.
 *
 * An explicit theme always wins. Otherwise, on an interactive terminal the
 * operator picks from the available themes; non-interactive callers (CI,
 * `setup:*` pipelines) get null so the command can fail loudly instead of
 * guessing. Kept Magento-free and side-effect-free so it is unit testable: the
 * actual prompt is injected as a callable.
 */
final class ThemeSelector
{
    /**
     * @param string[] $available
     * @param callable(string[]):?string $picker returns the chosen theme, or null when cancelled
     */
    public static function resolve(
        string $optionTheme,
        array $available,
        bool $interactive,
        callable $picker
    ): ?string {
        if ($optionTheme !== '') {
            return $optionTheme;
        }

        if (!$interactive || $available === []) {
            return null;
        }

        return $picker($available);
    }
}
