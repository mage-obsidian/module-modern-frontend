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
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;

/**
 * Publishes the i18n runtime config (locale + dictionary URL) as a JSON data
 * block, consumed by the JS translation layer.
 *
 * Renders the block inline (no .phtml) on purpose: the module may be symlinked
 * outside the Magento root in dev, which Magento's template path validation
 * rejects — building the markup in PHP sidesteps that entirely.
 */
class I18nRuntime extends AbstractBlock
{
    /**
     * @param Context $context
     * @param ViteResolver $viteResolver
     * @param PageConfigRenderer $pageConfigRenderer
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ViteResolver $viteResolver,
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
            PageConfigRenderer::I18N,
            $this->viteResolver->getI18nRuntimeConfig()
        );
    }
}
