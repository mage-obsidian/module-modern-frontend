<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg\Builder;

use DateTimeImmutable;
use DateTimeZone;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\WebPageBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\JsonLdRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WebPageBuilderTest extends TestCase
{
    private WebPageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new WebPageBuilder();
    }

    public function testReturnsNothingWithoutUrl(): void
    {
        $this->assertSame([], $this->builder->build(['name' => 'Home']));
        $this->assertSame([], $this->builder->build(['url' => '  ']));
    }

    public function testDefaultsToWebPageAndDerivesIdFromUrl(): void
    {
        $node = $this->builder->build(['url' => 'https://acme.test/about']);

        $this->assertSame([
            '@type' => 'WebPage',
            '@id' => 'https://acme.test/about#webpage',
            'url' => 'https://acme.test/about',
        ], $node);
    }

    #[DataProvider('supportedTypeProvider')]
    public function testKeepsSupportedPageTypes(string $type): void
    {
        $node = $this->builder->build(['url' => 'https://acme.test/', 'type' => $type]);

        $this->assertSame($type, $node['@type']);
    }

    public static function supportedTypeProvider(): array
    {
        return [
            ['WebPage'],
            ['CollectionPage'],
            ['ItemPage'],
            ['SearchResultsPage'],
            ['AboutPage'],
            ['ContactPage'],
            ['CheckoutPage'],
            ['ProfilePage'],
            ['QAPage'],
            ['FAQPage'],
        ];
    }

    public function testFallsBackToWebPageForAnUnknownType(): void
    {
        $node = $this->builder->build(['url' => 'https://acme.test/', 'type' => 'NotAThing']);

        $this->assertSame('WebPage', $node['@type']);
    }

    public function testNormalisesMagentoDateTimesToIso8601(): void
    {
        $node = $this->builder->build([
            'url' => 'https://acme.test/',
            'datePublished' => '2026-01-02 03:04:05',
            'dateModified' => '2026-08-30 12:00:00',
        ]);

        $this->assertSame('2026-01-02T03:04:05+00:00', $node['datePublished']);
        $this->assertSame('2026-08-30T12:00:00+00:00', $node['dateModified']);
    }

    public function testAcceptsDateTimeObjectsAndKeepsTheirOffset(): void
    {
        $node = $this->builder->build([
            'url' => 'https://acme.test/',
            'dateModified' => new DateTimeImmutable('2026-08-30 09:00:00', new DateTimeZone('America/New_York')),
        ]);

        $this->assertSame('2026-08-30T09:00:00-04:00', $node['dateModified']);
    }

    public function testFallsBackToDatePublishedWhenNoModificationDate(): void
    {
        $node = $this->builder->build([
            'url' => 'https://acme.test/',
            'datePublished' => '2026-01-02 03:04:05',
        ]);

        $this->assertSame('2026-01-02T03:04:05+00:00', $node['dateModified']);
    }

    public function testDropsUnparseableAndZeroDates(): void
    {
        $node = $this->builder->build([
            'url' => 'https://acme.test/',
            'datePublished' => '0000-00-00 00:00:00',
            'dateModified' => 'never',
        ]);

        $this->assertArrayNotHasKey('datePublished', $node);
        $this->assertArrayNotHasKey('dateModified', $node);
    }

    public function testOmitsEveryReferenceThatHasNoId(): void
    {
        $node = $this->builder->build([
            'url' => 'https://acme.test/',
            'isPartOfId' => null,
            'breadcrumbId' => '',
            'mainEntityId' => null,
            'publisherId' => '',
            'primaryImageOfPage' => '',
        ]);

        foreach (['isPartOf', 'breadcrumb', 'mainEntity', 'publisher', 'primaryImageOfPage'] as $property) {
            $this->assertArrayNotHasKey($property, $node);
        }
    }

    public function testGoldenJsonLdForAProductItemPage(): void
    {
        $node = $this->builder->build([
            'type' => 'ItemPage',
            'url' => 'https://acme.test/joust-duffle-bag.html',
            'name' => 'Joust Duffle Bag',
            'description' => 'A roomy duffle.',
            'inLanguage' => 'en-US',
            'isPartOfId' => 'https://acme.test/#website',
            'breadcrumbId' => 'https://acme.test/joust-duffle-bag.html#breadcrumb',
            'mainEntityId' => 'https://acme.test/joust-duffle-bag.html#product',
            'publisherId' => 'https://acme.test/#organization',
            'primaryImageOfPage' => 'https://acme.test/media/joust.jpg',
            'datePublished' => '2025-06-01 10:00:00',
            'dateModified' => '2026-08-29 18:30:00',
        ]);

        $this->assertSame(
            '<script type="application/ld+json">{"@context":"https://schema.org",'
            . '"@type":"ItemPage","@id":"https://acme.test/joust-duffle-bag.html#webpage",'
            . '"url":"https://acme.test/joust-duffle-bag.html","name":"Joust Duffle Bag",'
            . '"description":"A roomy duffle.","inLanguage":"en-US",'
            . '"isPartOf":{"@id":"https://acme.test/#website"},'
            . '"breadcrumb":{"@id":"https://acme.test/joust-duffle-bag.html#breadcrumb"},'
            . '"mainEntity":{"@id":"https://acme.test/joust-duffle-bag.html#product"},'
            . '"primaryImageOfPage":{"@type":"ImageObject","url":"https://acme.test/media/joust.jpg"},'
            . '"datePublished":"2025-06-01T10:00:00+00:00","dateModified":"2026-08-29T18:30:00+00:00",'
            . '"publisher":{"@id":"https://acme.test/#organization"}}</script>',
            (new JsonLdRenderer())->render($node)
        );
    }
}
