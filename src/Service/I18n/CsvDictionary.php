<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\I18n;

/**
 * Pure read/merge/write helpers for Magento i18n CSV dictionaries
 * (`<component>/i18n/<locale>.csv`, rows of `"phrase","translation"`).
 *
 * Kept free of IO so the merge semantics are unit-testable; the CLI command
 * owns the actual file reads/writes.
 */
class CsvDictionary
{
    /**
     * Parse CSV content into a phrase => translation map. Malformed/short rows
     * are skipped; a row with only a phrase maps to itself.
     *
     * @param string $contents
     * @return array<string, string>
     */
    public function parse(string $contents): array
    {
        $dictionary = [];
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $row = str_getcsv($line, ',', '"', '\\');
            if (!isset($row[0]) || $row[0] === '' || $row[0] === null) {
                continue;
            }
            $phrase = $row[0];
            $dictionary[$phrase] = $row[1] ?? $phrase;
        }

        return $dictionary;
    }

    /**
     * Merge newly collected phrases into an existing dictionary. Existing
     * translations are preserved; new phrases default to themselves (identity)
     * so translators can fill them in.
     *
     * @param array<string, string> $existing
     * @param string[] $phrases
     * @return array<string, string>
     */
    public function merge(array $existing, array $phrases): array
    {
        $merged = $existing;
        foreach ($phrases as $phrase) {
            if (!array_key_exists($phrase, $merged)) {
                $merged[$phrase] = $phrase;
            }
        }

        return $merged;
    }

    /**
     * Render a dictionary back to CSV, sorted by phrase for a stable diff.
     *
     * @param array<string, string> $dictionary
     * @return string
     */
    public function render(array $dictionary): string
    {
        ksort($dictionary);
        $lines = [];
        foreach ($dictionary as $phrase => $translation) {
            $lines[] = $this->quote((string)$phrase) . ',' . $this->quote((string)$translation);
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }

    /**
     * Wrap a value in double quotes, RFC 4180 style (`"` escaped as `""`), so
     * every field is quoted — matching Magento's i18n CSV convention and keeping
     * phrases with commas intact.
     */
    private function quote(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Count how many of the given phrases are absent from the dictionary.
     *
     * @param array<string, string> $existing
     * @param string[] $phrases
     * @return int
     */
    public function countNew(array $existing, array $phrases): int
    {
        $new = 0;
        foreach (array_unique($phrases) as $phrase) {
            if (!array_key_exists($phrase, $existing)) {
                $new++;
            }
        }

        return $new;
    }
}
