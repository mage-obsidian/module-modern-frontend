<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Model\SchemaOrg\CurrentPageSchemaProvider;
use MageObsidian\ModernFrontend\Model\SchemaOrg\JsonLdRenderer;

/**
 * Template-facing API for schema.org structured data, usable from both `.phtml`
 * (via this ViewModel) and `.twig` (via the `json_ld` helper that proxies the
 * matching block method).
 */
class SchemaOrg implements ArgumentInterface
{
    public function __construct(
        private readonly CurrentPageSchemaProvider $provider,
        private readonly JsonLdRenderer $renderer,
        private readonly ConfigProvider $configProvider
    ) {
    }

    /**
     * Whether auto-emission is enabled for the current store.
     */
    public function isEnabled(): bool
    {
        return $this->configProvider->isStructuredDataEnabled();
    }

    /**
     * The `<script>` tags for every schema.org node inferred from the current
     * page (Organization, WebSite, BreadcrumbList, Product as applicable).
     */
    public function getCurrentPageJsonLd(): string
    {
        return $this->renderer->renderMany($this->provider->getCurrentPageNodes());
    }

    /**
     * Escape hatch for custom schema.org types from a template (e.g. FAQPage):
     * wraps arbitrary data in a node and renders the `<script>` tag.
     *
     * @param string $type schema.org `@type` (e.g. "FAQPage").
     * @param array<string,mixed> $data Node body (without `@context`/`@type`).
     */
    public function renderJsonLd(string $type, array $data = []): string
    {
        return $this->renderer->render(['@type' => $type] + $data);
    }
}
