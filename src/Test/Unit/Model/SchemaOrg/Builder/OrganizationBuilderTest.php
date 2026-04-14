<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg\Builder;

use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\OrganizationBuilder;
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
        $node = $this->builder->build('Acme', 'https://acme.test/', 'https://acme.test/logo.png');

        $this->assertSame([
            '@type' => 'Organization',
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'logo' => 'https://acme.test/logo.png',
        ], $node);
    }

    public function testOmitsLogoWhenNull(): void
    {
        $node = $this->builder->build('Acme', 'https://acme.test/');

        $this->assertArrayNotHasKey('logo', $node);
    }

    public function testOmitsLogoWhenEmptyString(): void
    {
        $node = $this->builder->build('Acme', 'https://acme.test/', '');

        $this->assertArrayNotHasKey('logo', $node);
    }
}
