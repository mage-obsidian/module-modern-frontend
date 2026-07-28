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
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;

/**
 * Publishes where the icon set is served from as `window.__MAGE_OBSIDIAN_ICONS__`.
 *
 * Only the server can know that URL — it carries the static version and the
 * resolved theme — and the Vue `Icon` component needs the same one, or an island
 * whose markup contains an icon cannot be adopted.
 *
 * Inline (no .phtml) and through SecureHtmlRenderer for the same reasons as
 * {@see I18nRuntime}: the module may be symlinked outside the Magento root, and
 * a raw <script> is blocked on pages enforcing a strict script-src.
 */
class IconRuntime extends AbstractBlock
{
    /**
     * @param Context $context
     * @param ViteResolver $viteResolver
     * @param SecureHtmlRenderer $secureRenderer
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ViteResolver $viteResolver,
        private readonly SecureHtmlRenderer $secureRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        $config = json_encode(
            ['baseUrl' => $this->viteResolver->getIconBaseUrl()],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return $this->secureRenderer->renderTag(
            'script',
            [],
            "window.__MAGE_OBSIDIAN_ICONS__ = {$config};",
            false
        );
    }
}
