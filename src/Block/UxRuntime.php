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
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Service\PageConfigRenderer;

/**
 * Publishes the storefront UX settings as a JSON data block.
 *
 * Renders inline, like the i18n and section-data runtimes: building the markup
 * in PHP sidesteps Magento's template path validation, which rejects a module
 * symlinked outside the root.
 */
class UxRuntime extends AbstractBlock
{
    /**
     * @param Context $context
     * @param ConfigProvider $configProvider
     * @param PageConfigRenderer $pageConfigRenderer
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ConfigProvider $configProvider,
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
            PageConfigRenderer::UX,
            [
                'optimistic' => $this->configProvider->isOptimisticUiEnabled(),
                'summaryCountsQty' => $this->configProvider->doesCartSummaryCountQty(),
            ]
        );
    }
}
