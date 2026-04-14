<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg;

use InvalidArgumentException;

/**
 * Serializes a schema.org node into a `<script type="application/ld+json">` tag.
 *
 * Pure (no Magento dependencies) so the escaping rules and the `@context`/`@type`
 * contract are unit-testable in isolation; the builders produce the node, this
 * renders it. `@context` is injected here so a node is the single source for it.
 */
class JsonLdRenderer
{
    public const string CONTEXT = 'https://schema.org';

    /**
     * Render one node. An empty node renders nothing (callers pass the result
     * straight through, so a missing product/breadcrumb simply emits no markup).
     *
     * @param array<string,mixed> $node A schema.org node WITHOUT `@context`; must carry `@type`.
     *
     * @return string The `<script>` tag, or '' when $node is empty.
     * @throws InvalidArgumentException When a non-empty node lacks `@type`, or is not JSON-encodable.
     */
    public function render(array $node): string
    {
        if ($node === []) {
            return '';
        }

        if (!isset($node['@type'])) {
            throw new InvalidArgumentException('A schema.org node must declare an "@type".');
        }

        // `@context` first, then the node. Union (not array_merge) preserves the
        // node's own key order and never lets data clobber the context.
        $document = ['@context' => self::CONTEXT] + $node;

        try {
            // JSON_HEX_TAG escapes < and > (so an embedded "</script>" can never
            // close the tag), which makes JSON_UNESCAPED_SLASHES safe and yields
            // clean, readable URLs in the output.
            $json = json_encode(
                $document,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new InvalidArgumentException(
                sprintf('schema.org node is not JSON-encodable: %s', $e->getMessage()),
                0,
                $e
            );
        }

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * Render several nodes as separate adjacent `<script>` tags (Google reads
     * each independently; one tag per node keeps a malformed node from voiding
     * the rest). Empty nodes are skipped.
     *
     * @param list<array<string,mixed>> $nodes
     *
     * @return string
     */
    public function renderMany(array $nodes): string
    {
        $out = '';
        foreach ($nodes as $node) {
            $out .= $this->render($node);
        }

        return $out;
    }
}
