<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg;

use MageObsidian\ModernFrontend\Model\SchemaOrg\PageTypeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PageTypeResolverTest extends TestCase
{
    private const array MAP = [
        'catalog_product_view' => 'ItemPage',
        'catalog_category_view' => 'CollectionPage',
        'catalogsearch_result_index' => 'SearchResultsPage',
        'contact_index_index' => 'ContactPage',
        'customer_*' => 'ProfilePage',
    ];

    private PageTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PageTypeResolver(self::MAP);
    }

    #[DataProvider('handleProvider')]
    public function testResolvesTheConfiguredType(string $handle, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolve($handle));
    }

    public static function handleProvider(): array
    {
        return [
            'pdp' => ['catalog_product_view', 'ItemPage'],
            'category' => ['catalog_category_view', 'CollectionPage'],
            'search' => ['catalogsearch_result_index', 'SearchResultsPage'],
            'contact' => ['contact_index_index', 'ContactPage'],
            'cms home falls back' => ['cms_index_index', 'WebPage'],
            'unknown handle falls back' => ['vendor_thing_index', 'WebPage'],
            'empty handle falls back' => ['', 'WebPage'],
            'whitespace only falls back' => ['   ', 'WebPage'],
        ];
    }

    public function testMatchesARouteWildcardWhenNoExactHandleIsMapped(): void
    {
        $this->assertSame('ProfilePage', $this->resolver->resolve('customer_account_index'));
        $this->assertSame('ProfilePage', $this->resolver->resolve('customer_address_form'));
    }

    public function testExactHandleWinsOverTheRouteWildcard(): void
    {
        $resolver = new PageTypeResolver([
            'customer_*' => 'ProfilePage',
            'customer_account_login' => 'WebPage',
        ]);

        $this->assertSame('WebPage', $resolver->resolve('customer_account_login'));
    }

    public function testHonoursACustomDefault(): void
    {
        $resolver = new PageTypeResolver([], 'CollectionPage');

        $this->assertSame('CollectionPage', $resolver->resolve('anything_at_all'));
    }

    public function testIgnoresAnEmptyMappedValue(): void
    {
        $resolver = new PageTypeResolver(['cms_page_view' => '']);

        $this->assertSame('WebPage', $resolver->resolve('cms_page_view'));
    }
}
