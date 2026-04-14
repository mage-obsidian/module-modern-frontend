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
 * Builds a `WebSite` schema.org node. When a search URL template is provided it
 * attaches the `SearchAction` that enables Google's sitelinks search box.
 *
 * Pure: the search URL template (with its `{search_term_string}` placeholder) is
 * resolved by the caller; this only assembles the node.
 */
class WebSiteBuilder
{
    /**
     * @param string $name Site name.
     * @param string $url Canonical site URL.
     * @param string|null $searchUrlTemplate Full search URL containing the
     *        `{search_term_string}` placeholder; omitted when null/empty.
     *
     * @return array<string,mixed>
     */
    public function build(string $name, string $url, ?string $searchUrlTemplate = null): array
    {
        $node = [
            '@type' => 'WebSite',
            'name' => $name,
            'url' => $url,
        ];

        if ($searchUrlTemplate !== null && $searchUrlTemplate !== '') {
            $node['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchUrlTemplate,
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $node;
    }
}
