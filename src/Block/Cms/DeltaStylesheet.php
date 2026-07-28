<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Block\Cms;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\Service\Cms\DeltaStylesheet as DeltaStylesheetService;

/**
 * The `<link>` to the stylesheet for classes the build never saw.
 *
 * Emitted only while there is a delta, so a store whose build is up to date —
 * the normal state — ships nothing at all. That makes the empty↔non-empty flip
 * the only thing that changes the page's HTML; every later change to the CSS
 * itself rides the ETag and leaves the full page cache alone.
 *
 * Rendered as a child of `head.additional`, which the theme renders after its
 * own stylesheet, so the `utilities` layer these rules join is already declared.
 */
class DeltaStylesheet extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly DeltaStylesheetService $delta,
        private readonly UrlInterface $url,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->delta->hasDelta()) {
            return '';
        }

        return '<link rel="stylesheet" href="'
            . $this->escapeUrl($this->url->getUrl('mage-obsidian/cms/css'))
            . '"/>';
    }
}
