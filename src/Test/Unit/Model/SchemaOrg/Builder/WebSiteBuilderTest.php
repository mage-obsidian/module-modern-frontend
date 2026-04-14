<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg\Builder;

use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\WebSiteBuilder;
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
        $node = $this->builder->build(
            'Acme',
            'https://acme.test/',
            'https://acme.test/catalogsearch/result/?q={search_term_string}'
        );

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
        $node = $this->builder->build('Acme', 'https://acme.test/');

        $this->assertArrayNotHasKey('potentialAction', $node);
        $this->assertSame('WebSite', $node['@type']);
    }
}
