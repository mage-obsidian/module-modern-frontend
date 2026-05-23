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
use MageObsidian\ModernFrontend\ViewModel\SectionDataConfig;

/**
 * Publishes the section-store invalidation config (lifetime + expirable sections)
 * as the global `window.__MAGE_OBSIDIAN_SECTIONS__`, consumed by the JS
 * customer-data bridge to age stale sections out.
 *
 * Renders inline (no .phtml) like the i18n runtime: the module may be symlinked
 * outside the Magento root in dev, which Magento's template path validation
 * rejects — building the markup in PHP sidesteps that entirely.
 */
class SectionDataRuntime extends AbstractBlock
{
    /**
     * @param Context $context
     * @param SectionDataConfig $sectionDataConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly SectionDataConfig $sectionDataConfig,
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
            $this->sectionDataConfig->getConfig(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return "<script>window.__MAGE_OBSIDIAN_SECTIONS__ = {$config};</script>";
    }
}
