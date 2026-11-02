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
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\Service\PageConfigRenderer;
use MageObsidian\ModernFrontend\Service\RuntimeScriptReader;

class SectionPrefetchRuntime extends AbstractBlock
{
    private const SCRIPT_PATH = '/frontend/runtime/section-prefetch.head.js';

    private const MODULE_NAME = 'MageObsidian_ModernFrontend';

    private const SECTION_LOAD_ROUTE = 'customer/section/load';

    public function __construct(
        Context $context,
        private readonly PageConfigRenderer $pageConfigRenderer,
        private readonly SecureHtmlRenderer $secureRenderer,
        private readonly RuntimeScriptReader $scriptReader,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string[]
     */
    public function getSections(): array
    {
        $sections = $this->getData('sections');
        if (!is_array($sections)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn($name): string => is_string($name) ? trim($name) : '', $sections),
            static fn(string $name): bool => $name !== ''
        )));
    }

    protected function _toHtml(): string
    {
        $sections = $this->getSections();
        if ($sections === []) {
            return '';
        }

        $script = $this->scriptReader->read(self::MODULE_NAME, self::SCRIPT_PATH);
        if ($script === '') {
            return '';
        }

        return $this->pageConfigRenderer->render(
            PageConfigRenderer::SECTION_PREFETCH,
            ['url' => $this->getSectionLoadUrl($sections), 'sections' => $sections]
        ) . $this->secureRenderer->renderTag('script', [], $script, false);
    }

    /**
     * @param string[] $sections
     */
    private function getSectionLoadUrl(array $sections): string
    {
        return $this->getUrl(
            self::SECTION_LOAD_ROUTE,
            [
                '_query' => [
                    'sections' => implode(',', $sections),
                    'force_new_section_timestamp' => 'true',
                ],
            ]
        );
    }
}
