<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\ViewModel\SchemaOrg;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Auto-emits the current page's schema.org JSON-LD. Wired into `before.body.end`
 * (JSON-LD is valid anywhere in the document for Google), consistent with the
 * other MageObsidian runtime blocks.
 *
 * Rendered inline in PHP (no .phtml) for the same reason as I18nRuntime: the
 * module may be symlinked outside the Magento root in dev. Structured data is an
 * SEO enhancement, never page-critical, so a failure is logged and swallowed
 * rather than allowed to break the page.
 */
class SchemaOrgRuntime extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly SchemaOrg $schemaOrg,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->schemaOrg->isEnabled()) {
            return '';
        }

        try {
            return $this->schemaOrg->getCurrentPageJsonLd();
        } catch (Throwable $e) {
            $this->logger->error('MageObsidian: failed to render structured data.', ['exception' => $e]);
            return '';
        }
    }
}
