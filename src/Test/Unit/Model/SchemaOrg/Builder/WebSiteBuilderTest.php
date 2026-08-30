<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg\Builder;

use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\WebSiteBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\JsonLdRenderer;
use PHPUnit\Framework\TestCase;

class WebSiteBuilderTest extends TestCase
{
    private WebSiteBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new WebSiteBuilder();
    }

    public function testAttachesSearchActionWhenTemplateProvided(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'searchUrlTemplate' => 'https://acme.test/catalogsearch/result/?q={search_term_string}',
        ]);

        $this->assertSame([
            '@type' => 'WebSite',
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => 'https://acme.test/catalogsearch/result/?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ], $node);
    }

    public function testOmitsSearchActionWhenNoTemplate(): void
    {
        $node = $this->builder->build(['name' => 'Acme', 'url' => 'https://acme.test/']);

        $this->assertArrayNotHasKey('potentialAction', $node);
        $this->assertSame('WebSite', $node['@type']);
    }

    public function testReturnsNothingWithoutNameOrUrl(): void
    {
        $this->assertSame([], $this->builder->build(['url' => 'https://acme.test/']));
        $this->assertSame([], $this->builder->build(['name' => 'Acme']));
    }

    public function testLinksPublisherByReference(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'publisherId' => 'https://acme.test/#organization',
        ]);

        $this->assertSame(['@id' => 'https://acme.test/#organization'], $node['publisher']);
    }

    public function testOmitsPublisherWhenNoOrganizationId(): void
    {
        $node = $this->builder->build(['name' => 'Acme', 'url' => 'https://acme.test/', 'publisherId' => '']);

        $this->assertArrayNotHasKey('publisher', $node);
    }

    public function testGoldenJsonLdForAFullyConfiguredWebSite(): void
    {
        $node = $this->builder->build([
            '@id' => 'https://acme.test/#website',
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'description' => 'Bags built to last.',
            'inLanguage' => 'en-US',
            'publisherId' => 'https://acme.test/#organization',
            'searchUrlTemplate' => 'https://acme.test/catalogsearch/result/?q={search_term_string}',
        ]);

        $this->assertSame(
            '<script type="application/ld+json">{"@context":"https://schema.org",'
            . '"@type":"WebSite","@id":"https://acme.test/#website","name":"Acme",'
            . '"url":"https://acme.test/","description":"Bags built to last.","inLanguage":"en-US",'
            . '"publisher":{"@id":"https://acme.test/#organization"},'
            . '"potentialAction":{"@type":"SearchAction","target":{"@type":"EntryPoint",'
            . '"urlTemplate":"https://acme.test/catalogsearch/result/?q={search_term_string}"},'
            . '"query-input":"required name=search_term_string"}}</script>',
            (new JsonLdRenderer())->render($node)
        );
    }
}
