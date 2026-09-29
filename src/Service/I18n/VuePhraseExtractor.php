<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\I18n;

/**
 * Extracts translatable phrases from `.vue` (and any JS) source using the same
 * `$t('...')` call signature Magento already recognizes for static JS
 * translation. Pure and stateless so it is unit-testable without the framework.
 */
class VuePhraseExtractor
{
    /**
     * Matches `$t('phrase')` / `$t("phrase")`, tolerating the opposite quote and
     * backslash-escaped delimiters inside the string. Mirrors Magento's
     * `mage_translation_static` pattern.
     */
    private const PATTERN = '~\$t\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1~s';

    /**
     * Return the unique phrases found in the given source, in first-seen order.
     *
     * @param string $contents
     * @return string[]
     */
    public function extractFromString(string $contents): array
    {
        if (!preg_match_all(self::PATTERN, $contents, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $phrases = [];
        foreach ($matches as $match) {
            $quote = $match[1];
            $phrase = str_replace('\\' . $quote, $quote, $match[2]);
            if ($phrase !== '' && !in_array($phrase, $phrases, true)) {
                $phrases[] = $phrase;
            }
        }

        return $phrases;
    }
}
