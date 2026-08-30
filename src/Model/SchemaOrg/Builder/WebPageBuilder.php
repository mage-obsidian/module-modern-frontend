<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg\Builder;

class WebPageBuilder
{
    use NodeValues;

    public const string DEFAULT_TYPE = 'WebPage';

    private const array SUPPORTED_TYPES = [
        'WebPage',
        'CollectionPage',
        'ItemPage',
        'SearchResultsPage',
        'AboutPage',
        'ContactPage',
        'CheckoutPage',
        'ProfilePage',
        'QAPage',
        'FAQPage',
    ];

    public function build(array $data): array
    {
        $url = $this->text($data['url'] ?? null);
        if ($url === null) {
            return [];
        }

        $node = [
            '@type' => $this->resolveType($data['type'] ?? null),
            '@id' => $this->text($data['@id'] ?? null) ?? $url . '#webpage',
            'url' => $url,
        ];

        $name = $this->text($data['name'] ?? null);
        if ($name !== null) {
            $node['name'] = $name;
        }

        $description = $this->text($data['description'] ?? null);
        if ($description !== null) {
            $node['description'] = $description;
        }

        $inLanguage = $this->text($data['inLanguage'] ?? null);
        if ($inLanguage !== null) {
            $node['inLanguage'] = $inLanguage;
        }

        $isPartOf = $this->reference($data['isPartOfId'] ?? null);
        if ($isPartOf !== null) {
            $node['isPartOf'] = $isPartOf;
        }

        $breadcrumb = $this->reference($data['breadcrumbId'] ?? null);
        if ($breadcrumb !== null) {
            $node['breadcrumb'] = $breadcrumb;
        }

        $mainEntity = $this->reference($data['mainEntityId'] ?? null);
        if ($mainEntity !== null) {
            $node['mainEntity'] = $mainEntity;
        }

        $image = $this->text($data['primaryImageOfPage'] ?? null);
        if ($image !== null) {
            $node['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $image,
            ];
        }

        $datePublished = $this->isoDateTime($data['datePublished'] ?? null);
        if ($datePublished !== null) {
            $node['datePublished'] = $datePublished;
        }

        $dateModified = $this->isoDateTime($data['dateModified'] ?? null) ?? $datePublished;
        if ($dateModified !== null) {
            $node['dateModified'] = $dateModified;
        }

        $publisher = $this->reference($data['publisherId'] ?? null);
        if ($publisher !== null) {
            $node['publisher'] = $publisher;
        }

        return $node;
    }

    private function resolveType(mixed $type): string
    {
        $candidate = $this->text($type);

        return $candidate !== null && in_array($candidate, self::SUPPORTED_TYPES, true)
            ? $candidate
            : self::DEFAULT_TYPE;
    }
}
