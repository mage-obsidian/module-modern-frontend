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
 * Request-scoped collector of the eager island components rendered on the page.
 *
 * The island bootstrap discovers eager markers and dynamically imports each
 * component with a runtime-resolved URL, which Vite cannot preannounce — so the
 * browser only discovers each component's static dependency chain (customer-data
 * → section-store → store → pinia) serially, one round-trip per hop. Recording
 * the eager components here lets {@see \MageObsidian\ModernFrontend\Block\IslandsRuntime}
 * emit `<link rel="modulepreload">` for that chain up front and collapse the
 * waterfall. Shared as a singleton so the ViewModel that renders the markers and
 * the block that emits the bootstrap see the same set.
 */
class EagerIslandRegistry
{
    /**
     * Output-relative chunk paths (manifest `file` values), used as a set.
     *
     * @var array<string, true>
     */
    private array $components = [];

    /**
     * Record an eager component by its build-output-relative chunk path
     * (e.g. "MageObsidian_Storefront/components/wishlist/WishlistCount.js").
     *
     * @param string $componentFile
     */
    public function register(string $componentFile): void
    {
        $this->components[$componentFile] = true;
    }

    /**
     * Every eager component recorded so far, deduplicated.
     *
     * @return string[]
     */
    public function all(): array
    {
        return array_keys($this->components);
    }
}
