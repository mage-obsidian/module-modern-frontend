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
 * Publishes where the icon set is served from as a JSON data block.
 *
 * Only the server can know that URL — it carries the static version and the
 * resolved theme — and the Vue `Icon` component needs the same one, or an island
 * whose markup contains an icon cannot be adopted.
 *
 * Inline (no .phtml) for the same reason as {@see I18nRuntime}: the module may
 * be symlinked outside the Magento root.
 */
class IconRuntime extends AbstractBlock
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
            PageConfigRenderer::ICONS,
            ['baseUrl' => $this->viteResolver->getIconBaseUrl()]
        );
    }
}
