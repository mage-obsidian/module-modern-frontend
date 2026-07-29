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
use MageObsidian\ModernFrontend\ViewModel\PrePaintConfig;

class PrePaintRuntime extends AbstractBlock
{
    private const SCRIPT_PATH = '/frontend/runtime/prepaint.head.js';

    private const MODULE_NAME = 'MageObsidian_ModernFrontend';

    public function __construct(
        Context $context,
        private readonly PrePaintConfig $prePaintConfig,
        private readonly SecureHtmlRenderer $secureRenderer,
        private readonly Reader $moduleReader,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if ($this->prePaintConfig->isEmpty()) {
            return '';
        }

        $script = $this->readScript();
        if ($script === '') {
            return '';
        }

        $config = json_encode(
            $this->prePaintConfig->getConfig(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return $this->secureRenderer->renderTag(
            'script',
            [],
            "window.__MAGE_OBSIDIAN_PREPAINT__ = {$config};" . $script,
            false
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
