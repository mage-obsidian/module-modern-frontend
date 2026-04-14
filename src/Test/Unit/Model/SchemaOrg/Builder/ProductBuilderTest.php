<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg\Builder;

use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\ProductBuilder;
use PHPUnit\Framework\TestCase;

class ProductBuilderTest extends TestCase
{
    private ProductBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProductBuilder();
    }

    public function testBuildsFullProductWithOffer(): void
    {
        $node = $this->builder->build([
            'name' => 'Tote Bag',
            'url' => 'https://acme.test/tote',
            'sku' => 'TOTE-1',
            'description' => 'A roomy tote.',
            'image' => 'https://acme.test/media/tote.jpg',
            'brand' => 'Acme',
            'price' => 29.9,
            'priceCurrency' => 'USD',
            'availability' => 'InStock',
        ]);

        $this->assertSame([
            '@type' => 'Product',
            'name' => 'Tote Bag',
            'image' => ['https://acme.test/media/tote.jpg'],
            'description' => 'A roomy tote.',
            'sku' => 'TOTE-1',
            'brand' => ['@type' => 'Brand', 'name' => 'Acme'],
            'offers' => [
                '@type' => 'Offer',
                'price' => '29.90',
                'priceCurrency' => 'USD',
                'url' => 'https://acme.test/tote',
                'availability' => 'https://schema.org/InStock',
            ],
        ], $node);
    }

    public function testOmitsOfferWhenNoPrice(): void
    {
        $node = $this->builder->build(['name' => 'Tote Bag', 'sku' => 'TOTE-1']);

        $this->assertArrayNotHasKey('offers', $node);
        $this->assertSame('Product', $node['@type']);
    }

    public function testNormalizesAvailabilityTokenButKeepsAbsoluteUrl(): void
    {
        $tokened = $this->builder->build(['name' => 'X', 'price' => 1, 'availability' => 'OutOfStock']);
        $this->assertSame('https://schema.org/OutOfStock', $tokened['offers']['availability']);

        $absolute = $this->builder->build([
            'name' => 'X',
            'price' => 1,
            'availability' => 'https://schema.org/PreOrder',
        ]);
        $this->assertSame('https://schema.org/PreOrder', $absolute['offers']['availability']);
    }

    public function testAcceptsImageAsListAndReindexes(): void
    {
        $node = $this->builder->build([
            'name' => 'X',
            'image' => [3 => 'https://acme.test/a.jpg', 7 => 'https://acme.test/b.jpg'],
        ]);

        $this->assertSame(
            ['https://acme.test/a.jpg', 'https://acme.test/b.jpg'],
            $node['image']
        );
    }

    public function testFormatsPriceAsPlainDecimalString(): void
    {
        $node = $this->builder->build(['name' => 'X', 'price' => 1000]);

        // No thousands separator; two decimals; string type.
        $this->assertSame('1000.00', $node['offers']['price']);
    }

    public function testOmitsOptionalFieldsWhenEmpty(): void
    {
        $node = $this->builder->build(['name' => 'Bare']);

        $this->assertSame(['@type' => 'Product', 'name' => 'Bare'], $node);
    }
}
