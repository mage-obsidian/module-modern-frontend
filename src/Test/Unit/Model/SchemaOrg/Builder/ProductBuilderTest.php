<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg\Builder;

use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\ProductBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\JsonLdRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'url' => 'https://acme.test/tote',
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

    public function testReturnsNothingWithoutAName(): void
    {
        $this->assertSame([], $this->builder->build(['sku' => 'TOTE-1', 'price' => 10]));
    }

    public function testEmitsUrlAndIdOnTheProductNode(): void
    {
        $node = $this->builder->build([
            '@id' => 'https://acme.test/tote#product',
            'name' => 'Tote Bag',
            'url' => 'https://acme.test/tote',
        ]);

        $this->assertSame('https://acme.test/tote#product', $node['@id']);
        $this->assertSame('https://acme.test/tote', $node['url']);
    }

    public function testEmitsMpnAndCategory(): void
    {
        $node = $this->builder->build([
            'name' => 'Tote Bag',
            'mpn' => 'ACME-TOTE-01',
            'category' => 'Gear/Bags',
        ]);

        $this->assertSame('ACME-TOTE-01', $node['mpn']);
        $this->assertSame('Gear/Bags', $node['category']);
    }

    #[DataProvider('gtinProvider')]
    public function testPicksTheGtinPropertyMatchingTheDigitCount(string $raw, array $expected): void
    {
        $node = $this->builder->build(['name' => 'X', 'gtin' => $raw]);

        foreach ($expected as $property => $value) {
            $this->assertSame($value, $node[$property] ?? null, $property);
        }
    }

    public static function gtinProvider(): array
    {
        return [
            'ean 13' => ['4006381333931', ['gtin13' => '4006381333931', 'gtin' => '4006381333931']],
            'upc 12' => ['012345678905', ['gtin12' => '012345678905', 'gtin' => '012345678905']],
            'gtin 8' => ['96385074', ['gtin8' => '96385074', 'gtin' => '96385074']],
            'gtin 14' => ['00012345678905', ['gtin14' => '00012345678905', 'gtin' => '00012345678905']],
            'separators stripped' => ['4-006381-333931', ['gtin13' => '4006381333931']],
            'odd length keeps the generic property' => ['12345', ['gtin' => '12345', 'gtin13' => null]],
        ];
    }

    public function testOmitsGtinWhenTheAttributeCarriesNoDigits(): void
    {
        $node = $this->builder->build(['name' => 'X', 'gtin' => 'n/a']);

        $this->assertArrayNotHasKey('gtin', $node);
    }

    public function testEmitsItemConditionAndPriceValidUntilOnTheOffer(): void
    {
        $node = $this->builder->build([
            'name' => 'X',
            'price' => 10,
            'priceCurrency' => 'USD',
            'itemCondition' => 'NewCondition',
            'priceValidUntil' => '2027-08-30 00:00:00',
        ]);

        $this->assertSame('https://schema.org/NewCondition', $node['offers']['itemCondition']);
        $this->assertSame('2027-08-30', $node['offers']['priceValidUntil']);
    }

    public function testBuildsAnAggregateOfferForAPriceRange(): void
    {
        $node = $this->builder->build([
            'name' => 'Hoodie',
            'lowPrice' => 42,
            'highPrice' => 68.5,
            'offerCount' => 6,
            'priceCurrency' => 'USD',
            'availability' => 'InStock',
        ]);

        $this->assertSame([
            '@type' => 'AggregateOffer',
            'lowPrice' => '42.00',
            'highPrice' => '68.50',
            'offerCount' => 6,
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
        ], $node['offers']);
    }

    public function testKeepsAPlainOfferWhenTheRangeCollapsesToOnePrice(): void
    {
        $node = $this->builder->build([
            'name' => 'Tote',
            'price' => 29.9,
            'lowPrice' => 29.9,
            'highPrice' => 29.9,
            'priceCurrency' => 'USD',
        ]);

        $this->assertSame('Offer', $node['offers']['@type']);
        $this->assertSame('29.90', $node['offers']['price']);
    }

    public function testFallsBackToTheLowPriceWhenNoFinalPriceIsKnown(): void
    {
        $node = $this->builder->build(['name' => 'Tote', 'lowPrice' => 15]);

        $this->assertSame('Offer', $node['offers']['@type']);
        $this->assertSame('15.00', $node['offers']['price']);
    }

    public function testDeduplicatesImages(): void
    {
        $node = $this->builder->build([
            'name' => 'X',
            'image' => ['https://acme.test/a.jpg', 'https://acme.test/a.jpg', '', null],
        ]);

        $this->assertSame(['https://acme.test/a.jpg'], $node['image']);
    }

    public function testGoldenJsonLdForAFullyPopulatedProduct(): void
    {
        $node = $this->builder->build([
            '@id' => 'https://acme.test/joust-duffle-bag.html#product',
            'name' => 'Joust Duffle Bag',
            'url' => 'https://acme.test/joust-duffle-bag.html',
            'sku' => '24-MB01',
            'gtin' => '4006381333931',
            'mpn' => 'ACME-24MB01',
            'brand' => 'Acme',
            'description' => 'A roomy duffle.',
            'image' => ['https://acme.test/media/joust.jpg'],
            'price' => 34,
            'priceCurrency' => 'USD',
            'availability' => 'InStock',
            'itemCondition' => 'NewCondition',
            'priceValidUntil' => '2027-08-30',
        ]);

        $this->assertSame(
            '<script type="application/ld+json">{"@context":"https://schema.org",'
            . '"@type":"Product","@id":"https://acme.test/joust-duffle-bag.html#product",'
            . '"name":"Joust Duffle Bag","image":["https://acme.test/media/joust.jpg"],'
            . '"description":"A roomy duffle.","url":"https://acme.test/joust-duffle-bag.html",'
            . '"sku":"24-MB01","mpn":"ACME-24MB01","gtin13":"4006381333931","gtin":"4006381333931",'
            . '"brand":{"@type":"Brand","name":"Acme"},'
            . '"offers":{"@type":"Offer","price":"34.00","priceCurrency":"USD",'
            . '"url":"https://acme.test/joust-duffle-bag.html","availability":"https://schema.org/InStock",'
            . '"itemCondition":"https://schema.org/NewCondition","priceValidUntil":"2027-08-30"}}</script>',
            (new JsonLdRenderer())->render($node)
        );
    }
}
