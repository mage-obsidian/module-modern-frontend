<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg;

use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\BreadcrumbListBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\OrganizationBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\ProductBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\WebSiteBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\CurrentPageSchemaProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the Magento→builder data extraction with mocked Magento services
 * and the real (pure) builders. Mocks Magento framework types, so it runs in
 * the full suite (phpunit.xml), excluded from the standalone CI suite.
 */
class CurrentPageSchemaProviderTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private CatalogData&MockObject $catalogData;
    private ImageHelper&MockObject $imageHelper;
    private LogoPathResolver&MockObject $logoPathResolver;
    private Store&MockObject $store;
    private CurrentPageSchemaProvider $provider;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->catalogData = $this->createMock(CatalogData::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->logoPathResolver = $this->createMock(LogoPathResolver::class);
        $this->store = $this->createMock(Store::class);

        $this->store->method('getId')->willReturn(1);
        $this->store->method('getFrontendName')->willReturn('Acme Store');
        $this->store->method('getCurrentCurrencyCode')->willReturn('USD');
        $this->store->method('getBaseUrl')->willReturnCallback(
            static fn($type = UrlInterface::URL_TYPE_LINK): string => $type === UrlInterface::URL_TYPE_MEDIA
                ? 'https://acme.test/media/'
                : 'https://acme.test/'
        );
        $this->storeManager->method('getStore')->willReturn($this->store);

        $this->provider = new CurrentPageSchemaProvider(
            $this->storeManager,
            $this->scopeConfig,
            $this->catalogData,
            $this->imageHelper,
            $this->logoPathResolver,
            new OrganizationBuilder(),
            new WebSiteBuilder(),
            new BreadcrumbListBuilder(),
            new ProductBuilder()
        );
    }

    public function testEmitsOrganizationAndWebSiteSiteWide(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('Acme');
        $this->logoPathResolver->method('getPath')->willReturn('default/logo.png');
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $nodes = $this->provider->getCurrentPageNodes();

        $this->assertCount(2, $nodes);
        $this->assertSame([
            '@type' => 'Organization',
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'logo' => 'https://acme.test/media/logo/default/logo.png',
        ], $nodes[0]);
        $this->assertSame('WebSite', $nodes[1]['@type']);
        $this->assertSame(
            'https://acme.test/catalogsearch/result/?q={search_term_string}',
            $nodes[1]['potentialAction']['target']['urlTemplate']
        );
    }

    public function testFallsBackToFrontendNameAndOmitsLogoWhenUnset(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $nodes = $this->provider->getCurrentPageNodes();

        $this->assertSame('Acme Store', $nodes[0]['name']);
        $this->assertArrayNotHasKey('logo', $nodes[0]);
    }

    public function testAddsBreadcrumbListWithHomePrepended(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('Acme');
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getProduct')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([
            'category3' => ['label' => 'Bags', 'link' => 'https://acme.test/bags'],
            'product' => ['label' => 'Tote'],
        ]);

        $nodes = $this->provider->getCurrentPageNodes();
        $breadcrumb = $this->findNode($nodes, 'BreadcrumbList');

        $this->assertNotNull($breadcrumb);
        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://acme.test/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Bags', 'item' => 'https://acme.test/bags'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Tote'],
        ], $breadcrumb['itemListElement']);
    }

    public function testAddsProductNodeWithOfferAndPlainTextDescription(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('Acme');
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($this->buildProduct());

        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('https://acme.test/media/catalog/tote.jpg');

        $product = $this->findNode($this->provider->getCurrentPageNodes(), 'Product');

        $this->assertNotNull($product);
        $this->assertSame('Tote Bag', $product['name']);
        $this->assertSame('TOTE-1', $product['sku']);
        $this->assertSame('A roomy tote.', $product['description']);
        $this->assertSame(['https://acme.test/media/catalog/tote.jpg'], $product['image']);
        $this->assertSame([
            '@type' => 'Offer',
            'price' => '29.90',
            'priceCurrency' => 'USD',
            'url' => 'https://acme.test/tote',
            'availability' => 'https://schema.org/InStock',
        ], $product['offers']);
    }

    public function testProductImageFailureDoesNotVoidProductNode(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('Acme');
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($this->buildProduct());

        // A theme missing the image id makes the helper throw; schema must survive.
        $this->imageHelper->method('init')->willThrowException(new \RuntimeException('no image id'));

        $product = $this->findNode($this->provider->getCurrentPageNodes(), 'Product');

        $this->assertNotNull($product);
        $this->assertArrayNotHasKey('image', $product);
        $this->assertSame('Tote Bag', $product['name']);
    }

    private function buildProduct(): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Tote Bag');
        $product->method('getSku')->willReturn('TOTE-1');
        $product->method('getProductUrl')->willReturn('https://acme.test/tote');
        $product->method('isAvailable')->willReturn(true);
        $product->method('getFinalPrice')->willReturn(29.9);
        $product->method('getData')->willReturnCallback(
            static fn(string $key = '', $index = null) => $key === 'short_description'
                ? 'A <b>roomy</b> tote.'
                : ''
        );

        return $product;
    }

    /**
     * @param list<array<string,mixed>> $nodes
     *
     * @return array<string,mixed>|null
     */
    private function findNode(array $nodes, string $type): ?array
    {
        foreach ($nodes as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        return null;
    }
}
