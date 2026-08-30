<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg\Builder;

class WebSiteBuilder
{
    use NodeValues;

    public function build(array $data): array
    {
        $name = $this->text($data['name'] ?? null);
        $url = $this->text($data['url'] ?? null);
        if ($name === null || $url === null) {
            return [];
        }

        $node = ['@type' => 'WebSite'];

        $id = $this->text($data['@id'] ?? null);
        if ($id !== null) {
            $node['@id'] = $id;
        }

        $node['name'] = $name;
        $node['url'] = $url;

        $description = $this->text($data['description'] ?? null);
        if ($description !== null) {
            $node['description'] = $description;
        }

        $inLanguage = $this->text($data['inLanguage'] ?? null);
        if ($inLanguage !== null) {
            $node['inLanguage'] = $inLanguage;
        }

        $publisher = $this->reference($data['publisherId'] ?? null);
        if ($publisher !== null) {
            $node['publisher'] = $publisher;
        }

        $searchUrlTemplate = $this->text($data['searchUrlTemplate'] ?? null);
        if ($searchUrlTemplate !== null) {
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
