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
 * Builds a `Product` schema.org node (with a nested `Offer`).
 *
 * Pure: the caller extracts the product data from Magento into a plain array.
 * Optional fields are omitted when empty; the `Offer` is only attached when a
 * price is present. `aggregateRating`/`review` are intentionally out of scope
 * (deferred) to avoid emitting rating data the storefront cannot back.
 */
class ProductBuilder
{
    private const string AVAILABILITY_BASE = 'https://schema.org/';

    /**
     * @param array{
     *     name?: string|null,
     *     url?: string|null,
     *     sku?: string|null,
     *     description?: string|null,
     *     image?: string|list<string>|null,
     *     brand?: string|null,
     *     price?: string|float|int|null,
     *     priceCurrency?: string|null,
     *     availability?: string|null
     * } $data
     *
     * @return array<string,mixed>
     */
    public function build(array $data): array
    {
        $node = [
            '@type' => 'Product',
            'name' => (string)($data['name'] ?? ''),
        ];

        if (!empty($data['image'])) {
            $node['image'] = is_array($data['image'])
                ? array_values($data['image'])
                : [(string)$data['image']];
        }

        if (!empty($data['description'])) {
            $node['description'] = (string)$data['description'];
        }

        if (!empty($data['sku'])) {
            $node['sku'] = (string)$data['sku'];
        }

        if (!empty($data['brand'])) {
            $node['brand'] = [
                '@type' => 'Brand',
                'name' => (string)$data['brand'],
            ];
        }

        $offer = $this->buildOffer($data);
        if ($offer !== []) {
            $node['offers'] = $offer;
        }

        return $node;
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed> The Offer node, or [] when there is no price.
     */
    private function buildOffer(array $data): array
    {
        $price = $data['price'] ?? null;
        if ($price === null || $price === '') {
            return [];
        }

        $offer = [
            '@type' => 'Offer',
            'price' => $this->formatPrice($price),
        ];

        if (!empty($data['priceCurrency'])) {
            $offer['priceCurrency'] = (string)$data['priceCurrency'];
        }

        if (!empty($data['url'])) {
            $offer['url'] = (string)$data['url'];
        }

        if (!empty($data['availability'])) {
            $offer['availability'] = $this->normalizeAvailability((string)$data['availability']);
        }

        return $offer;
    }

    /**
     * schema.org expects price as a plain decimal string (no thousands separator).
     *
     * @param string|float|int $price
     */
    private function formatPrice(string|float|int $price): string
    {
        return number_format((float)$price, 2, '.', '');
    }

    /**
     * Accept either a short token ("InStock") or an already-absolute schema.org
     * enum URL, normalizing the former to the canonical URL Google expects.
     */
    private function normalizeAvailability(string $availability): string
    {
        if (str_starts_with($availability, 'http://') || str_starts_with($availability, 'https://')) {
            return $availability;
        }

        return self::AVAILABILITY_BASE . $availability;
    }
}
