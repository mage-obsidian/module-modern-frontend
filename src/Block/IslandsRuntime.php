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
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;

/**
 * Loads the page-level island bootstrap once per page. The bootstrap discovers
 * the markers emitted by `renderVueComponent` and mounts each as a Vue island
 * (lazily by default); it loads Vue and i18n only if a marker is present, so a
 * page without islands pays nothing.
 *
 * Renders the script inline (no .phtml) on purpose: the module may be symlinked
 * outside the Magento root in dev, which Magento's template path validation
 * rejects — building the markup in PHP sidesteps that entirely.
 */
class IslandsRuntime extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly ViteResolver $viteResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        $url = htmlspecialchars(
            $this->viteResolver->getIslandsRuntimeUrl(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return "<script type=\"module\" src=\"{$url}\"></script>";
    }
}
