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
use MageObsidian\ModernFrontend\Service\RuntimeScriptReader;
use MageObsidian\ModernFrontend\ViewModel\PrePaintConfig;

class PrePaintRuntime extends AbstractBlock
{
    private const SCRIPT_PATH = '/frontend/runtime/prepaint.head.js';

    private const MODULE_NAME = 'MageObsidian_ModernFrontend';

    public function __construct(
        Context $context,
        private readonly PrePaintConfig $prePaintConfig,
        private readonly SecureHtmlRenderer $secureRenderer,
        private readonly RuntimeScriptReader $scriptReader,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if ($this->prePaintConfig->isEmpty()) {
            return '';
        }

        $script = $this->scriptReader->read(self::MODULE_NAME, self::SCRIPT_PATH);
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
}
