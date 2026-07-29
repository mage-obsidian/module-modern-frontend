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
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;

/**
 * Publishes the storefront UX settings as `window.__MAGE_OBSIDIAN_UX__`.
 *
 * Renders inline through SecureHtmlRenderer, like the i18n and section-data
 * runtimes: the tag carries a CSP nonce (a raw <script> is blocked on checkout
 * and customer-account pages) and building the markup in PHP sidesteps Magento's
 * template path validation, which rejects a module symlinked outside the root.
 */
class UxRuntime extends AbstractBlock
{
    public const string GLOBAL_NAME = '__MAGE_OBSIDIAN_UX__';

    /**
     * @param Context $context
     * @param ConfigProvider $configProvider
     * @param SecureHtmlRenderer $secureRenderer
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ConfigProvider $configProvider,
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
            [
                'optimistic' => $this->configProvider->isOptimisticUiEnabled(),
                'summaryCountsQty' => $this->configProvider->doesCartSummaryCountQty(),
            ],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return $this->secureRenderer->renderTag(
            'script',
            [],
            'window.' . self::GLOBAL_NAME . ' = ' . $config . ';',
            false
        );
    }
}
