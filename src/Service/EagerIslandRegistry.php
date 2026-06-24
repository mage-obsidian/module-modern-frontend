<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service;

/**
 * Request-scoped dedup tracker for the modulepreload hints emitted by the eager
 * islands.
 *
 * Each eager island emits `<link rel="modulepreload">` for its own dependency
 * chunks at its render point (so islands rendered after the page bootstrap —
 * e.g. body-end drawers/toasts — are still covered, and header islands emit
 * their hints early). Many islands share the same chunks (vue, pinia,
 * customer-data, section-store…); this records what has already been emitted in
 * the request so each chunk is hinted exactly once. Shared as a singleton so
 * every island marker rendered in the request sees the same set.
 */
class EagerIslandRegistry
{
    /**
     * Already-emitted preload hrefs, used as a set.
     *
     * @var array<string, true>
     */
    private array $emitted = [];

    /**
     * Return the subset of $urls not emitted yet this request, recording them so
     * later calls do not repeat them.
     *
     * @param string[] $urls
     *
     * @return string[]
     */
    public function take(array $urls): array
    {
        $fresh = [];
        foreach ($urls as $url) {
            if (!isset($this->emitted[$url])) {
                $this->emitted[$url] = true;
                $fresh[] = $url;
            }
        }

        return $fresh;
    }
}
