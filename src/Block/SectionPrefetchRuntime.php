<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Block;

use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class SectionPrefetchRuntime extends AbstractBlock
{
    private const SCRIPT_PATH = '/frontend/runtime/section-prefetch.head.js';

    private const MODULE_NAME = 'MageObsidian_ModernFrontend';

    private const SECTION_LOAD_ROUTE = 'customer/section/load';

    public function __construct(
        Context $context,
        private readonly SecureHtmlRenderer $secureRenderer,
        private readonly Reader $moduleReader,
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

        $script = $this->readScript();
        if ($script === '') {
            return '';
        }

        $config = json_encode(
            ['url' => $this->getSectionLoadUrl($sections), 'sections' => $sections],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return $this->secureRenderer->renderTag(
            'script',
            [],
            "window.__MAGE_OBSIDIAN_SECTION_PREFETCH_CONFIG__ = {$config};" . $script,
            false
        );
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

    private function readScript(): string
    {
        $path = $this->moduleReader->getModuleDir('view', self::MODULE_NAME) . self::SCRIPT_PATH;
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        return (string)file_get_contents($path);
    }
}
