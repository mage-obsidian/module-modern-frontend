<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\Service\PageConfigRenderer;
use MageObsidian\ModernFrontend\ViewModel\SectionDataConfig;

/**
 * Publishes the section-store invalidation config (lifetime + expirable sections)
 * as a JSON data block, consumed by the JS customer-data bridge to age stale
 * sections out.
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
     * @param PageConfigRenderer $pageConfigRenderer
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly SectionDataConfig $sectionDataConfig,
        private readonly PageConfigRenderer $pageConfigRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        return $this->pageConfigRenderer->render(
            PageConfigRenderer::SECTIONS,
            $this->sectionDataConfig->getConfig()
        );
    }
}
