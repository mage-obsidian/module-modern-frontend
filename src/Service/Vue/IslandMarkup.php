<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Vue;

/**
 * Vue's fragment anchors, so no template hardcodes an HTML comment.
 *
 * Pure (no Magento dependencies) so the rules are unit-testable in isolation,
 * and injectable so a project can replace them through di.xml.
 */
class IslandMarkup
{
    public const string FRAGMENT_START = '<!--[-->';
    public const string FRAGMENT_END = '<!--]-->';
    public const string VOID = '<!---->';

    /**
     * Wrap a rendered loop in the fragment anchors Vue expects, mirroring `v-for`.
     *
     * @param string $html
     *
     * @return string
     */
    public function list(string $html): string
    {
        return self::FRAGMENT_START . trim($html) . self::FRAGMENT_END;
    }

    /**
     * Emit the markup or Vue's empty-branch placeholder, mirroring a `v-if` that
     * has no `v-else` (Vue renders a taken if/else branch with no anchor).
     *
     * @param bool $condition
     * @param string $html
     *
     * @return string
     */
    public function if(bool $condition, string $html): string
    {
        return $condition ? trim($html) : self::VOID;
    }
}
