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
    use NodeValues;

    private const array GTIN_PROPERTY_BY_LENGTH = [
        8 => 'gtin8',
        12 => 'gtin12',
        13 => 'gtin13',
        14 => 'gtin14',
    ];

    /**
     * @param array{
     *     name?: string|null,
     *     url?: string|null,
     *     '@id'?: string|null,
     *     sku?: string|null,
     *     description?: string|null,
     *     image?: string|list<string>|null,
     *     brand?: string|null,
     *     gtin?: string|null,
     *     mpn?: string|null,
     *     category?: string|null,
     *     price?: string|float|int|null,
     *     lowPrice?: string|float|int|null,
     *     highPrice?: string|float|int|null,
     *     offerCount?: int|null,
     *     priceCurrency?: string|null,
     *     priceValidUntil?: string|\DateTimeInterface|null,
     *     itemCondition?: string|null,
     *     availability?: string|null
     * } $data
     *
     * @return array<string,mixed>
     */
    public function build(array $data): array
    {
        $name = $this->text($data['name'] ?? null);
        if ($name === null) {
            return [];
        }

        $node = ['@type' => 'Product'];

        $id = $this->text($data['@id'] ?? null);
        if ($id !== null) {
            $node['@id'] = $id;
        }

        $node['name'] = $name;

        $images = $this->buildImages($data['image'] ?? null);
        if ($images !== []) {
            $node['image'] = $images;
        }

        $description = $this->text($data['description'] ?? null);
        if ($description !== null) {
            $node['description'] = $description;
        }

        $url = $this->text($data['url'] ?? null);
        if ($url !== null) {
            $node['url'] = $url;
        }

        $sku = $this->text($data['sku'] ?? null);
        if ($sku !== null) {
            $node['sku'] = $sku;
        }

        $mpn = $this->text($data['mpn'] ?? null);
        if ($mpn !== null) {
            $node['mpn'] = $mpn;
        }

        foreach ($this->buildGtin($data['gtin'] ?? null) as $property => $value) {
            $node[$property] = $value;
        }

        $brand = $this->text($data['brand'] ?? null);
        if ($brand !== null) {
            $node['brand'] = [
                '@type' => 'Brand',
                'name' => $brand,
            ];
        }

        $category = $this->text($data['category'] ?? null);
        if ($category !== null) {
            $node['category'] = $category;
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
        $offer = $this->buildOfferHead($data);
        if ($offer === []) {
            return [];
        }

        $currency = $this->text($data['priceCurrency'] ?? null);
        if ($currency !== null) {
            $offer['priceCurrency'] = $currency;
        }

        $url = $this->text($data['url'] ?? null);
        if ($url !== null) {
            $offer['url'] = $url;
        }

        $availability = $this->enumUrl($data['availability'] ?? null);
        if ($availability !== null) {
            $offer['availability'] = $availability;
        }

        $itemCondition = $this->enumUrl($data['itemCondition'] ?? null);
        if ($itemCondition !== null) {
            $offer['itemCondition'] = $itemCondition;
        }

        $priceValidUntil = $this->isoDate($data['priceValidUntil'] ?? null);
        if ($priceValidUntil !== null) {
            $offer['priceValidUntil'] = $priceValidUntil;
        }

        return $offer;
    }

    private function buildOfferHead(array $data): array
    {
        $low = $this->price($data['lowPrice'] ?? null);
        $high = $this->price($data['highPrice'] ?? null);

        if ($low !== null && $high !== null && $high !== $low) {
            $offer = [
                '@type' => 'AggregateOffer',
                'lowPrice' => $low,
                'highPrice' => $high,
            ];
            $offerCount = (int)($data['offerCount'] ?? 0);
            if ($offerCount > 0) {
                $offer['offerCount'] = $offerCount;
            }

            return $offer;
        }

        $price = $this->price($data['price'] ?? null) ?? $low;
        if ($price === null) {
            return [];
        }

        return [
            '@type' => 'Offer',
            'price' => $price,
        ];
    }

    private function buildGtin(mixed $gtin): array
    {
        $value = $this->text($gtin);
        if ($value === null) {
            return [];
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';
        if ($digits === '') {
            return [];
        }

        $property = self::GTIN_PROPERTY_BY_LENGTH[strlen($digits)] ?? null;

        return $property !== null ? [$property => $digits, 'gtin' => $digits] : ['gtin' => $digits];
    }

    private function buildImages(mixed $image): array
    {
        $candidates = is_array($image) ? $image : [$image];

        $urls = [];
        foreach ($candidates as $candidate) {
            $url = $this->text($candidate);
            if ($url !== null) {
                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    /**
     * schema.org expects price as a plain decimal string (no thousands separator).
     */
    private function price(mixed $price): ?string
    {
        if ($price === null || $price === '' || !is_scalar($price)) {
            return null;
        }

        return number_format((float)$price, 2, '.', '');
    }
}
