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
 * Publishes the i18n runtime config (locale + dictionary URL) as the global
 * `window.__MAGE_OBSIDIAN_I18N__`, consumed by the JS translation layer.
 *
 * Renders the script inline (no .phtml) on purpose: the module may be symlinked
 * outside the Magento root in dev, which Magento's template path validation
 * rejects — building the markup in PHP sidesteps that entirely.
 */
class I18nRuntime extends AbstractBlock
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
        $config = json_encode(
            $this->viteResolver->getI18nRuntimeConfig(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return "<script>window.__MAGE_OBSIDIAN_I18N__ = {$config};</script>";
    }
}
