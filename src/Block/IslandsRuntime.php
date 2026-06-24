<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\Service\EagerIslandRegistry;
use MageObsidian\ModernFrontend\Service\IslandManifest;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;

/**
 * Loads the page-level island bootstrap once per page. The bootstrap discovers
 * the markers emitted by `renderVueComponent` and mounts each as a Vue island
 * (lazily by default); it loads Vue and i18n only if a marker is present, so a
 * page without islands pays nothing.
 *
 * Before the bootstrap, it emits `<link rel="modulepreload">` for every eager
 * island's static dependency chunks (resolved from the Vite manifest). The
 * bootstrap imports each component with a runtime URL Vite cannot preannounce,
 * so without these hints the browser walks each component's chain (customer-data
 * → section-store → store → pinia/vue) one round-trip at a time; the hints let
 * it fetch the whole graph in parallel and collapse the waterfall.
 *
 * Renders inline (no .phtml) on purpose: the module may be symlinked outside the
 * Magento root in dev, which Magento's template path validation rejects —
 * building the markup in PHP sidesteps that entirely.
 */
class IslandsRuntime extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly ViteResolver $viteResolver,
        private readonly EagerIslandRegistry $eagerIslandRegistry,
        private readonly IslandManifest $islandManifest,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        $url = $this->escape($this->viteResolver->getIslandsRuntimeUrl());
        $script = "<script type=\"module\" src=\"{$url}\"></script>";

        $preloads = '';
        foreach ($this->islandManifest->getPreloadUrls($this->eagerIslandRegistry->all()) as $preloadUrl) {
            $preloads .= "<link rel=\"modulepreload\" href=\"{$this->escape($preloadUrl)}\"/>";
        }

        return $preloads . $script;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
