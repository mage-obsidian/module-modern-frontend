<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Cms;

/**
 * The class names written inside a piece of CMS content.
 *
 * Deliberately narrower than Tailwind's own extractor, which treats almost any
 * word as a candidate: the same function has to run over the build's snapshot
 * and over the content as it is now, and the difference between the two is what
 * gets compiled on the fly. Reading `class` attributes keeps that difference
 * made of things an author actually wrote, instead of prose that happens to
 * look like a utility.
 *
 * Pure and stateless so it is unit-testable without the framework.
 */
class ClassCandidates
{
    /** Both quote styles, and `class` may be spelled with any casing in pasted HTML. */
    private const string PATTERN = '~\bclass\s*=\s*(["\'])(?<value>.*?)\1~is';

    /**
     * @param string $html
     *
     * @return string[] Sorted and unique, so two extractions of the same content compare equal.
     */
    public static function extract(string $html): array
    {
        // A widget stores its parameters escaped, so a class list nested inside
        // one reads `class=&quot;p-4&quot;` and no quote-delimited match finds
        // it. Scanning the unescaped copy as well costs nothing: a stray class
        // picked up from escaped sample markup only means one more rule.
        $candidates = self::scan($html) + self::scan(html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        $candidates = array_keys($candidates);
        sort($candidates);

        return $candidates;
    }

    /**
     * @return array<string, true>
     */
    private static function scan(string $html): array
    {
        if (!preg_match_all(self::PATTERN, $html, $matches)) {
            return [];
        }

        $candidates = [];
        foreach ($matches['value'] as $value) {
            foreach (preg_split('~\s+~', trim($value)) ?: [] as $class) {
                if ($class !== '') {
                    $candidates[$class] = true;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param string[] ...$sets
     *
     * @return string[]
     */
    public static function merge(array ...$sets): array
    {
        $merged = array_unique(array_merge(...$sets));
        sort($merged);

        return array_values($merged);
    }
}
