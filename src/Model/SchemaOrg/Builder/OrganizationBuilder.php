<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg\Builder;

/**
 * Builds an `Organization` schema.org node (site-wide branding).
 *
 * Pure: takes already-resolved primitives and returns the node array. Optional
 * fields are omitted when empty so the output never carries null/empty values
 * (which would make the structured data invalid).
 */
class OrganizationBuilder
{
    /**
     * @param string $name Brand/store name.
     * @param string $url Canonical site URL.
     * @param string|null $logoUrl Absolute logo URL, omitted when null/empty.
     *
     * @return array<string,mixed>
     */
    public function build(string $name, string $url, ?string $logoUrl = null): array
    {
        $node = [
            '@type' => 'Organization',
            'name' => $name,
            'url' => $url,
        ];

        if ($logoUrl !== null && $logoUrl !== '') {
            $node['logo'] = $logoUrl;
        }

        return $node;
    }
}
