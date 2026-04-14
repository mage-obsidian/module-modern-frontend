<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg\Builder;

use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\BreadcrumbListBuilder;
use PHPUnit\Framework\TestCase;

class BreadcrumbListBuilderTest extends TestCase
{
    private BreadcrumbListBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new BreadcrumbListBuilder();
    }

    public function testBuildsPositionedListAndOmitsUrlOnTrailingCrumb(): void
    {
        $node = $this->builder->build([
            ['name' => 'Home', 'url' => 'https://acme.test/'],
            ['name' => 'Bags', 'url' => 'https://acme.test/bags'],
            ['name' => 'Tote', 'url' => null],
        ]);

        $this->assertSame('BreadcrumbList', $node['@type']);
        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://acme.test/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Bags', 'item' => 'https://acme.test/bags'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Tote'],
        ], $node['itemListElement']);
    }

    public function testRenumbersPositionsSkippingNamelessCrumbs(): void
    {
        $node = $this->builder->build([
            ['name' => 'Home', 'url' => 'https://acme.test/'],
            ['name' => '', 'url' => 'https://acme.test/ghost'],
            ['name' => 'Tote', 'url' => 'https://acme.test/tote'],
        ]);

        $positions = array_column($node['itemListElement'], 'position');
        $names = array_column($node['itemListElement'], 'name');

        $this->assertSame([1, 2], $positions);
        $this->assertSame(['Home', 'Tote'], $names);
    }

    public function testReturnsEmptyWhenNoValidCrumbs(): void
    {
        $this->assertSame([], $this->builder->build([]));
        $this->assertSame([], $this->builder->build([['name' => '', 'url' => 'x']]));
    }
}
