<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg\Builder;

use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\OrganizationBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\JsonLdRenderer;
use PHPUnit\Framework\TestCase;

class OrganizationBuilderTest extends TestCase
{
    private OrganizationBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new OrganizationBuilder();
    }

    public function testBuildsNodeWithLogo(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'logo' => 'https://acme.test/logo.png',
        ]);

        $this->assertSame([
            '@type' => 'Organization',
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'logo' => 'https://acme.test/logo.png',
        ], $node);
    }

    public function testOmitsLogoWhenNull(): void
    {
        $node = $this->builder->build(['name' => 'Acme', 'url' => 'https://acme.test/']);

        $this->assertArrayNotHasKey('logo', $node);
    }

    public function testOmitsLogoWhenEmptyString(): void
    {
        $node = $this->builder->build(['name' => 'Acme', 'url' => 'https://acme.test/', 'logo' => '']);

        $this->assertArrayNotHasKey('logo', $node);
    }

    public function testReturnsNothingWithoutNameOrUrl(): void
    {
        $this->assertSame([], $this->builder->build(['url' => 'https://acme.test/']));
        $this->assertSame([], $this->builder->build(['name' => 'Acme']));
        $this->assertSame([], $this->builder->build(['name' => '   ', 'url' => 'https://acme.test/']));
    }

    public function testKeepsOnlyAbsoluteHttpSameAsUrisAndDeduplicates(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'sameAs' => [
                'https://www.wikidata.org/wiki/Q1',
                'https://www.linkedin.com/company/acme',
                'https://www.wikidata.org/wiki/Q1',
                'www.facebook.com/acme',
                'mailto:hi@acme.test',
                '  ',
            ],
        ]);

        $this->assertSame([
            'https://www.wikidata.org/wiki/Q1',
            'https://www.linkedin.com/company/acme',
        ], $node['sameAs']);
    }

    public function testAcceptsSameAsAsNewlineSeparatedText(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'sameAs' => "https://x.com/acme\nhttps://www.instagram.com/acme\n",
        ]);

        $this->assertSame(['https://x.com/acme', 'https://www.instagram.com/acme'], $node['sameAs']);
    }

    public function testOmitsSameAsWhenNothingSurvives(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'sameAs' => ['not-a-uri', ''],
        ]);

        $this->assertArrayNotHasKey('sameAs', $node);
    }

    public function testBuildsPostalAddressFromPartialData(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'address' => [
                'streetAddress' => '1 Main St',
                'addressLocality' => 'Austin',
                'addressRegion' => 'Texas',
                'postalCode' => '78701',
                'addressCountry' => 'US',
            ],
        ]);

        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => '1 Main St',
            'addressLocality' => 'Austin',
            'addressRegion' => 'Texas',
            'postalCode' => '78701',
            'addressCountry' => 'US',
        ], $node['address']);
    }

    public function testOmitsAddressWhenEveryPartIsEmpty(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'address' => ['streetAddress' => null, 'addressLocality' => '', 'addressCountry' => null],
        ]);

        $this->assertArrayNotHasKey('address', $node);
    }

    public function testBuildsContactPointFromPhoneWithDefaultType(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'telephone' => '+1 555 0100',
        ]);

        $this->assertSame([
            ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'telephone' => '+1 555 0100'],
        ], $node['contactPoint']);
    }

    public function testHonoursConfiguredContactType(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'email' => 'sales@acme.test',
            'contactType' => 'sales',
            'areaServed' => 'US',
            'availableLanguage' => 'en',
        ]);

        $this->assertSame([
            [
                '@type' => 'ContactPoint',
                'contactType' => 'sales',
                'email' => 'sales@acme.test',
                'areaServed' => 'US',
                'availableLanguage' => 'en',
            ],
        ], $node['contactPoint']);
    }

    public function testOmitsContactPointWithoutPhoneOrEmail(): void
    {
        $node = $this->builder->build([
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'contactType' => 'sales',
        ]);

        $this->assertArrayNotHasKey('contactPoint', $node);
    }

    public function testGoldenJsonLdForAFullyConfiguredOrganization(): void
    {
        $node = $this->builder->build([
            '@id' => 'https://acme.test/#organization',
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'logo' => 'https://acme.test/media/logo.png',
            'description' => 'Bags built to last.',
            'telephone' => '+1 555 0100',
            'email' => 'hello@acme.test',
            'vatID' => 'US123456789',
            'contactType' => 'customer support',
            'address' => ['addressLocality' => 'Austin', 'addressCountry' => 'US'],
            'sameAs' => ['https://www.wikidata.org/wiki/Q1'],
        ]);

        $this->assertSame(
            '<script type="application/ld+json">{"@context":"https://schema.org",'
            . '"@type":"Organization","@id":"https://acme.test/#organization","name":"Acme",'
            . '"url":"https://acme.test/","logo":"https://acme.test/media/logo.png",'
            . '"description":"Bags built to last.","telephone":"+1 555 0100",'
            . '"email":"hello@acme.test","vatID":"US123456789",'
            . '"address":{"@type":"PostalAddress","addressLocality":"Austin","addressCountry":"US"},'
            . '"contactPoint":[{"@type":"ContactPoint","contactType":"customer support",'
            . '"telephone":"+1 555 0100","email":"hello@acme.test"}],'
            . '"sameAs":["https://www.wikidata.org/wiki/Q1"]}</script>',
            (new JsonLdRenderer())->render($node)
        );
    }
}
