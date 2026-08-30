<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg;

use DateTime;
use DateTimeZone;
use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfoBase;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\Design\Backend\Logo;
use Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\BreadcrumbListBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\OrganizationBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\ProductBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\WebPageBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\WebSiteBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\CurrentPageSchemaProvider;
use MageObsidian\ModernFrontend\Model\SchemaOrg\PageTypeResolver;
use MageObsidian\ModernFrontend\Model\SchemaOrg\SchemaOrgConfig;
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
    private SchemaOrgConfig&MockObject $config;
    private CatalogData&MockObject $catalogData;
    private ImageHelper&MockObject $imageHelper;
    private LogoPathResolver&MockObject $logoPathResolver;
    private PageConfig&MockObject $pageConfig;
    private CmsPage&MockObject $cmsPage;
    private UrlInterface&MockObject $url;
    private TimezoneInterface&MockObject $timezone;
    private RegionFactory&MockObject $regionFactory;
    private Store&MockObject $store;
    private CurrentPageSchemaProvider $provider;

    /** @var array<string,string|null> */
    private array $configValues = [];

    /** @var array<string,int> */
    private array $configInts = [];

    /** @var array<string,bool> */
    private array $configFlags = [];

    /** @var list<string> */
    private array $sameAs = [];

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->config = $this->createMock(SchemaOrgConfig::class);
        $this->catalogData = $this->createMock(CatalogData::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->logoPathResolver = $this->createMock(LogoPathResolver::class);
        $this->pageConfig = $this->createMock(PageConfig::class);
        $this->cmsPage = $this->createMock(CmsPage::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->store = $this->createMock(Store::class);

        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('date')->willReturnCallback(
            static fn($date = null, $locale = null, $useTimezone = true, $includeTime = true): DateTime
                => $date instanceof DateTime
                    ? $date->setTimezone(new DateTimeZone('UTC'))
                    : new DateTime('2026-08-30 00:00:00', new DateTimeZone('UTC'))
        );

        $this->store->method('getId')->willReturn(1);
        $this->store->method('getFrontendName')->willReturn('Acme Store');
        $this->store->method('getCurrentCurrencyCode')->willReturn('USD');
        $this->store->method('getBaseUrl')->willReturnCallback(
            static fn($type = UrlInterface::URL_TYPE_LINK): string => $type === UrlInterface::URL_TYPE_MEDIA
                ? 'https://acme.test/media/'
                : 'https://acme.test/'
        );
        $this->storeManager->method('getStore')->willReturn($this->store);

        $this->configValues = [SchemaOrgConfig::STORE_NAME => 'Acme'];
        $this->configInts = [SchemaOrgConfig::PRODUCT_IMAGE_LIMIT => 3];
        $this->configFlags = [SchemaOrgConfig::WEBPAGE_ENABLED => false];
        $this->sameAs = [];

        $this->config->method('getString')->willReturnCallback(
            fn(string $path, ?int $storeId = null): ?string => $this->configValues[$path] ?? null
        );
        $this->config->method('getInt')->willReturnCallback(
            fn(string $path, ?int $storeId = null): int => $this->configInts[$path] ?? 0
        );
        $this->config->method('isSetFlag')->willReturnCallback(
            fn(string $path, ?int $storeId = null): bool => $this->configFlags[$path] ?? false
        );
        $this->config->method('getSameAs')->willReturnCallback(fn(?int $storeId = null): array => $this->sameAs);
        $this->config->method('getInLanguage')->willReturn('en-US');

        $this->url->method('getCurrentUrl')->willReturn('https://acme.test/current');
        $this->timezone = $timezone;
        $this->regionFactory = $this->createMock(RegionFactory::class);
        $this->provider = $this->providerFor('cms_index_index');
    }

    private function providerFor(string $fullActionName): CurrentPageSchemaProvider
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getFullActionName')->willReturn($fullActionName);

        return new CurrentPageSchemaProvider(
            $this->storeManager,
            $this->config,
            $this->catalogData,
            $this->imageHelper,
            $this->logoPathResolver,
            $this->pageConfig,
            $request,
            $this->cmsPage,
            $this->timezone,
            $this->url,
            $this->regionFactory,
            new PageTypeResolver([
                'catalog_product_view' => 'ItemPage',
                'catalog_category_view' => 'CollectionPage',
                'catalogsearch_result_index' => 'SearchResultsPage',
            ]),
            new OrganizationBuilder(),
            new WebSiteBuilder(),
            new WebPageBuilder(),
            new BreadcrumbListBuilder(),
            new ProductBuilder()
        );
    }

    public function testEmitsOrganizationAndWebSiteSiteWide(): void
    {
        $this->logoPathResolver->method('getPath')->willReturn($this->headerLogoPath('default/logo.png'));
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $nodes = $this->provider->getCurrentPageNodes();

        $this->assertCount(2, $nodes);
        $this->assertSame([
            '@type' => 'Organization',
            '@id' => 'https://acme.test/#organization',
            'name' => 'Acme',
            'url' => 'https://acme.test/',
            'logo' => 'https://acme.test/media/logo/default/logo.png',
        ], $nodes[0]);
        $this->assertSame('WebSite', $nodes[1]['@type']);
        $this->assertSame('https://acme.test/#website', $nodes[1]['@id']);
        $this->assertSame(['@id' => 'https://acme.test/#organization'], $nodes[1]['publisher']);
        $this->assertSame('en-US', $nodes[1]['inLanguage']);
        $this->assertSame(
            'https://acme.test/catalogsearch/result/?q={search_term_string}',
            $nodes[1]['potentialAction']['target']['urlTemplate']
        );
    }

    public function testFallsBackToFrontendNameAndOmitsLogoWhenUnset(): void
    {
        $this->configValues[SchemaOrgConfig::STORE_NAME] = null;
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $nodes = $this->provider->getCurrentPageNodes();

        $this->assertSame('Acme Store', $nodes[0]['name']);
        $this->assertArrayNotHasKey('logo', $nodes[0]);
    }

    public function testConfiguredSeoLogoOverridesTheHeaderLogo(): void
    {
        $this->configValues[SchemaOrgConfig::ORGANIZATION_LOGO] = 'knowledge-panel.png';
        $this->logoPathResolver->method('getPath')->willReturn($this->headerLogoPath('default/logo.png'));
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $nodes = $this->provider->getCurrentPageNodes();

        $this->assertSame(
            'https://acme.test/media/mage_obsidian/seo/knowledge-panel.png',
            $nodes[0]['logo']
        );
    }

    public function testHeaderLogoUrlCarriesTheLogoDirectoryExactlyOnce(): void
    {
        $this->logoPathResolver->method('getPath')->willReturn($this->headerLogoPath('x.png'));
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $logo = $this->provider->getCurrentPageNodes()[0]['logo'];

        $this->assertSame('https://acme.test/media/logo/x.png', $logo);
        $this->assertSame(1, substr_count($logo, '/logo/'));
    }

    public function testHeaderLogoUrlAcceptsALeadingSlashFromTheResolver(): void
    {
        $this->logoPathResolver->method('getPath')->willReturn('/' . $this->headerLogoPath('x.png'));
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $this->assertSame(
            'https://acme.test/media/logo/x.png',
            $this->provider->getCurrentPageNodes()[0]['logo']
        );
    }

    public function testOmitsLogoWhenTheStoredLogoSrcIsBlank(): void
    {
        $this->logoPathResolver->method('getPath')->willReturn($this->headerLogoPath(''));
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $this->assertArrayNotHasKey('logo', $this->provider->getCurrentPageNodes()[0]);
    }

    public function testExtractsThePriceRangeIntoAnAggregateOffer(): void
    {
        $product = $this->buildProduct();
        $this->stubPriceRange($product, 42.0, 68.5);

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($product);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $offer = $this->findNode($this->provider->getCurrentPageNodes(), 'Product')['offers'];

        $this->assertSame('AggregateOffer', $offer['@type']);
        $this->assertSame('42.00', $offer['lowPrice']);
        $this->assertSame('68.50', $offer['highPrice']);
    }

    public function testKeepsAPlainOfferWhenTheExtractedRangeCollapses(): void
    {
        $product = $this->buildProduct();
        $this->stubPriceRange($product, 29.9, 29.9);

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($product);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $offer = $this->findNode($this->provider->getCurrentPageNodes(), 'Product')['offers'];

        $this->assertSame('Offer', $offer['@type']);
        $this->assertSame('29.90', $offer['price']);
    }

    public function testCountsChildProductsAsOfferCountOnAnAggregateOffer(): void
    {
        $product = $this->buildProduct();
        $this->stubPriceRange($product, 42.0, 68.5);
        $this->stubChildrenIds($product, [0 => [11, 12, 13]]);

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($product);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $offer = $this->findNode($this->provider->getCurrentPageNodes(), 'Product')['offers'];

        $this->assertSame(3, $offer['offerCount']);
    }

    public function testDoesNotFabricateAPriceValidUntilWhenNoSpecialPriceSuppliesOne(): void
    {
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($this->buildProduct());
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $offer = $this->findNode($this->provider->getCurrentPageNodes(), 'Product')['offers'];

        $this->assertArrayNotHasKey('priceValidUntil', $offer);
    }

    public function testShippedDefaultNeverFabricatesAPriceValidUntil(): void
    {
        $config = simplexml_load_file(__DIR__ . '/../../../../etc/config.xml');
        $seo = $config->default->mage_obsidian->seo;

        $this->assertSame('0', (string)$seo->price_valid_until_days);
    }

    public function testOmitsMainEntityWhenTheProductNodeIsNotEmitted(): void
    {
        $this->configFlags[SchemaOrgConfig::WEBPAGE_ENABLED] = true;
        $this->stubPageTitleAndDescription('Nameless', '');

        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('');
        $product->method('getProductUrl')->willReturn('https://acme.test/tote');
        $product->method('getData')->willReturn('');

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($product);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $nodes = $this->provider->getCurrentPageNodes();

        $this->assertNull($this->findNode($nodes, 'Product'));
        $this->assertArrayNotHasKey('mainEntity', $this->findNode($nodes, 'WebPage'));
    }

    public function testEveryWebPageReferencePointsAtAnEmittedNode(): void
    {
        $this->configFlags[SchemaOrgConfig::WEBPAGE_ENABLED] = true;
        $this->stubPageTitleAndDescription('Tote Bag', '');

        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('');
        $product->method('getProductUrl')->willReturn('https://acme.test/tote');
        $product->method('getData')->willReturn('');

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getProduct')->willReturn($product);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([
            'category3' => ['label' => 'Bags', 'link' => 'https://acme.test/bags'],
        ]);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $nodes = $this->provider->getCurrentPageNodes();
        $emitted = array_column($nodes, '@id');
        $webPage = $this->findNode($nodes, 'WebPage');

        foreach (['isPartOf', 'breadcrumb', 'mainEntity', 'publisher'] as $property) {
            if (isset($webPage[$property]['@id'])) {
                $this->assertContains($webPage[$property]['@id'], $emitted, $property);
            }
        }
    }

    public function testNeverLoadsARegionWhenNoAddressIsConfigured(): void
    {
        $regionFactory = $this->createMock(RegionFactory::class);
        $regionFactory->expects($this->never())->method('create');

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $this->regionFactory = $regionFactory;
        $this->providerFor('cms_index_index')->getCurrentPageNodes();
    }

    private function stubPriceRange(Product&MockObject $product, float $low, float $high): void
    {
        $minimal = $this->createMock(AmountInterface::class);
        $minimal->method('getValue')->willReturn($low);
        $maximal = $this->createMock(AmountInterface::class);
        $maximal->method('getValue')->willReturn($high);

        $finalPrice = $this->createMock(FinalPrice::class);
        $finalPrice->method('getMinimalPrice')->willReturn($minimal);
        $finalPrice->method('getMaximalPrice')->willReturn($maximal);

        $priceInfo = $this->createMock(PriceInfoBase::class);
        $priceInfo->method('getPrice')->with(FinalPrice::PRICE_CODE)->willReturn($finalPrice);

        $product->method('getPriceInfo')->willReturn($priceInfo);
    }

    private function stubChildrenIds(Product&MockObject $product, array $childrenIds): void
    {
        $type = $this->createMock(\Magento\Catalog\Model\Product\Type\AbstractType::class);
        $type->method('getChildrenIds')->willReturn($childrenIds);

        $product->method('getId')->willReturn(7);
        $product->method('getTypeInstance')->willReturn($type);
    }

    private function headerLogoPath(string $storedLogoSrc): string
    {
        return Logo::UPLOAD_DIR . '/' . $storedLogoSrc;
    }

    public function testEmitsSameAsDescriptionContactPointAndAddress(): void
    {
        $this->sameAs = ['https://www.wikidata.org/wiki/Q1', 'https://www.linkedin.com/company/acme'];
        $this->configValues[SchemaOrgConfig::ORGANIZATION_DESCRIPTION] = 'Bags built to last.';
        $this->configValues[SchemaOrgConfig::STORE_PHONE] = '+1 555 0100';
        $this->configValues[SchemaOrgConfig::STORE_EMAIL] = 'hello@acme.test';
        $this->configValues[SchemaOrgConfig::STORE_VAT_NUMBER] = 'US123456789';
        $this->configValues[SchemaOrgConfig::ORGANIZATION_CONTACT_TYPE] = 'customer support';
        $this->configValues[SchemaOrgConfig::STORE_STREET_LINE1] = '1 Main St';
        $this->configValues[SchemaOrgConfig::STORE_STREET_LINE2] = 'Suite 4';
        $this->configValues[SchemaOrgConfig::STORE_CITY] = 'Austin';
        $this->configValues[SchemaOrgConfig::STORE_REGION_ID] = 'Texas';
        $this->configValues[SchemaOrgConfig::STORE_POSTCODE] = '78701';
        $this->configValues[SchemaOrgConfig::STORE_COUNTRY_ID] = 'US';
        $this->configFlags[SchemaOrgConfig::ORGANIZATION_ADDRESS_ENABLED] = true;

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $organization = $this->provider->getCurrentPageNodes()[0];

        $this->assertSame($this->sameAs, $organization['sameAs']);
        $this->assertSame('Bags built to last.', $organization['description']);
        $this->assertSame('US123456789', $organization['vatID']);
        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => '1 Main St, Suite 4',
            'addressLocality' => 'Austin',
            'addressRegion' => 'Texas',
            'postalCode' => '78701',
            'addressCountry' => 'US',
        ], $organization['address']);
        $this->assertSame([
            [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'telephone' => '+1 555 0100',
                'email' => 'hello@acme.test',
            ],
        ], $organization['contactPoint']);
    }

    public function testFallsBackToTheDefaultMetaDescriptionForTheOrganization(): void
    {
        $this->configValues[SchemaOrgConfig::DEFAULT_DESCRIPTION] = 'Default store description.';
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $this->assertSame('Default store description.', $this->provider->getCurrentPageNodes()[0]['description']);
    }

    public function testOmitsAddressWhenTheFlagIsOff(): void
    {
        $this->configValues[SchemaOrgConfig::STORE_CITY] = 'Austin';
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $this->assertArrayNotHasKey('address', $this->provider->getCurrentPageNodes()[0]);
    }

    public function testAddsBreadcrumbListWithHomePrepended(): void
    {
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getProduct')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([
            'category3' => ['label' => 'Bags', 'link' => 'https://acme.test/bags'],
            'product' => ['label' => 'Tote'],
        ]);

        $nodes = $this->provider->getCurrentPageNodes();
        $breadcrumb = $this->findNode($nodes, 'BreadcrumbList');

        $this->assertNotNull($breadcrumb);
        $this->assertSame('https://acme.test/current#breadcrumb', $breadcrumb['@id']);
        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://acme.test/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Bags', 'item' => 'https://acme.test/bags'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Tote'],
        ], $breadcrumb['itemListElement']);
    }

    public function testAddsProductNodeWithOfferAndPlainTextDescription(): void
    {
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($this->buildProduct());

        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('https://acme.test/media/catalog/tote.jpg');

        $product = $this->findNode($this->provider->getCurrentPageNodes(), 'Product');

        $this->assertNotNull($product);
        $this->assertSame('https://acme.test/tote#product', $product['@id']);
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

    public function testMapsConfiguredAttributesToBrandGtinAndMpn(): void
    {
        $this->configValues[SchemaOrgConfig::PRODUCT_BRAND_ATTRIBUTE] = 'manufacturer';
        $this->configValues[SchemaOrgConfig::PRODUCT_GTIN_ATTRIBUTE] = 'ean';
        $this->configValues[SchemaOrgConfig::PRODUCT_MPN_ATTRIBUTE] = 'mpn';

        $product = $this->buildProduct();
        $product->method('getAttributeText')->willReturnCallback(
            static fn(string $code) => $code === 'manufacturer' ? 'Acme' : false
        );

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($product);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $node = $this->findNode($this->provider->getCurrentPageNodes(), 'Product');

        $this->assertSame(['@type' => 'Brand', 'name' => 'Acme'], $node['brand']);
        $this->assertSame('4006381333931', $node['gtin13']);
        $this->assertSame('ACME-TOTE-01', $node['mpn']);
    }

    public function testOmitsBrandGtinAndMpnWhenNoAttributeIsConfigured(): void
    {
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($this->buildProduct());
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $node = $this->findNode($this->provider->getCurrentPageNodes(), 'Product');

        foreach (['brand', 'gtin', 'gtin13', 'mpn'] as $property) {
            $this->assertArrayNotHasKey($property, $node);
        }
    }

    public function testEmitsItemConditionAndAPriceValidUntilHorizon(): void
    {
        $this->configValues[SchemaOrgConfig::PRODUCT_CONDITION] = 'NewCondition';
        $this->configInts[SchemaOrgConfig::PRICE_VALID_UNTIL_DAYS] = 30;

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($this->buildProduct());
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $offer = $this->findNode($this->provider->getCurrentPageNodes(), 'Product')['offers'];

        $this->assertSame('https://schema.org/NewCondition', $offer['itemCondition']);
        $this->assertSame('2026-09-29', $offer['priceValidUntil']);
    }

    public function testAnExpiredSpecialPriceNeverBecomesPriceValidUntil(): void
    {
        $this->configInts[SchemaOrgConfig::PRICE_VALID_UNTIL_DAYS] = 0;

        $product = $this->buildProduct(['special_to_date' => '2020-01-01 00:00:00']);

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($product);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $offer = $this->findNode($this->provider->getCurrentPageNodes(), 'Product')['offers'];

        $this->assertArrayNotHasKey('priceValidUntil', $offer);
    }

    public function testAnActiveSpecialPriceSuppliesPriceValidUntil(): void
    {
        $this->configInts[SchemaOrgConfig::PRICE_VALID_UNTIL_DAYS] = 365;

        $product = $this->buildProduct(['special_to_date' => '2026-12-24 00:00:00']);

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($product);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $offer = $this->findNode($this->provider->getCurrentPageNodes(), 'Product')['offers'];

        $this->assertSame('2026-12-24', $offer['priceValidUntil']);
    }

    public function testProductImageFailureDoesNotVoidProductNode(): void
    {
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

    public function testEmitsNoWebPageNodeWhenTheFlagIsOff(): void
    {
        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $this->assertNull($this->findNode($this->provider->getCurrentPageNodes(), 'WebPage'));
    }

    public function testEmitsAWebPageNodeForACmsPageWithItsUpdateTime(): void
    {
        $this->configFlags[SchemaOrgConfig::WEBPAGE_ENABLED] = true;
        $this->stubPageTitleAndDescription('About us', 'Who we are.');

        $this->cmsPage->method('getId')->willReturn(12);
        $this->cmsPage->method('getCreationTime')->willReturn('2025-02-01 08:00:00');
        $this->cmsPage->method('getUpdateTime')->willReturn('2026-08-29 18:30:00');

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $webPage = $this->findNode($this->provider->getCurrentPageNodes(), 'WebPage');

        $this->assertNotNull($webPage);
        $this->assertSame('https://acme.test/current#webpage', $webPage['@id']);
        $this->assertSame('https://acme.test/current', $webPage['url']);
        $this->assertSame('About us', $webPage['name']);
        $this->assertSame('Who we are.', $webPage['description']);
        $this->assertSame('en-US', $webPage['inLanguage']);
        $this->assertSame(['@id' => 'https://acme.test/#website'], $webPage['isPartOf']);
        $this->assertSame(['@id' => 'https://acme.test/#organization'], $webPage['publisher']);
        $this->assertSame('2025-02-01T08:00:00+00:00', $webPage['datePublished']);
        $this->assertSame('2026-08-29T18:30:00+00:00', $webPage['dateModified']);
    }

    public function testEmitsAnItemPageBoundToTheProductOnAPdp(): void
    {
        $this->configFlags[SchemaOrgConfig::WEBPAGE_ENABLED] = true;
        $this->stubPageTitleAndDescription('Tote Bag', '');

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(
            $this->buildProduct(['created_at' => '2025-06-01 10:00:00', 'updated_at' => '2026-08-01 09:00:00'])
        );
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('https://acme.test/media/catalog/tote.jpg');

        $nodes = $this->providerFor('catalog_product_view')->getCurrentPageNodes();
        $webPage = $this->findNode($nodes, 'ItemPage');

        $this->assertNotNull($webPage);
        $this->assertSame('https://acme.test/tote#webpage', $webPage['@id']);
        $this->assertSame('https://acme.test/tote', $webPage['url']);
        $this->assertSame(['@id' => 'https://acme.test/tote#product'], $webPage['mainEntity']);
        $this->assertSame(
            ['@type' => 'ImageObject', 'url' => 'https://acme.test/media/catalog/tote.jpg'],
            $webPage['primaryImageOfPage']
        );
        $this->assertSame('2025-06-01T10:00:00+00:00', $webPage['datePublished']);
        $this->assertSame('2026-08-01T09:00:00+00:00', $webPage['dateModified']);
    }

    public function testEmitsACollectionPageWithTheCategoryUrlAndDates(): void
    {
        $this->configFlags[SchemaOrgConfig::WEBPAGE_ENABLED] = true;
        $this->stubPageTitleAndDescription('Bags', 'Every bag we make.');

        $category = $this->createMock(Category::class);
        $category->method('getUrl')->willReturn('https://acme.test/gear/bags.html');
        $category->method('getData')->willReturnCallback(
            static fn(string $key = '', $index = null) => match ($key) {
                'created_at' => '2024-03-01 00:00:00',
                'updated_at' => '2026-08-20 11:00:00',
                default => null,
            }
        );

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);
        $this->catalogData->method('getCategory')->willReturn($category);

        $nodes = $this->providerFor('catalog_category_view')->getCurrentPageNodes();
        $webPage = $this->findNode($nodes, 'CollectionPage');

        $this->assertNotNull($webPage);
        $this->assertSame('https://acme.test/gear/bags.html', $webPage['url']);
        $this->assertSame('2024-03-01T00:00:00+00:00', $webPage['datePublished']);
        $this->assertSame('2026-08-20T11:00:00+00:00', $webPage['dateModified']);
    }

    public function testWebPageLinksTheBreadcrumbListById(): void
    {
        $this->configFlags[SchemaOrgConfig::WEBPAGE_ENABLED] = true;
        $this->stubPageTitleAndDescription('Bags', '');

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getProduct')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([
            'category3' => ['label' => 'Bags', 'link' => 'https://acme.test/bags'],
        ]);

        $nodes = $this->provider->getCurrentPageNodes();

        $this->assertSame(
            ['@id' => 'https://acme.test/current#breadcrumb'],
            $this->findNode($nodes, 'WebPage')['breadcrumb']
        );
        $this->assertSame(
            'https://acme.test/current#breadcrumb',
            $this->findNode($nodes, 'BreadcrumbList')['@id']
        );
    }

    public function testEmitsASearchResultsPageOnTheSearchHandle(): void
    {
        $this->configFlags[SchemaOrgConfig::WEBPAGE_ENABLED] = true;
        $this->stubPageTitleAndDescription('Search results', '');

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $nodes = $this->providerFor('catalogsearch_result_index')->getCurrentPageNodes();

        $this->assertNotNull($this->findNode($nodes, 'SearchResultsPage'));
    }

    private function stubPageTitleAndDescription(string $title, string $description): void
    {
        $pageTitle = $this->createMock(Title::class);
        $pageTitle->method('getShort')->willReturn($title);
        $pageTitle->method('get')->willReturn($title);

        $this->pageConfig->method('getTitle')->willReturn($pageTitle);
        $this->pageConfig->method('getDescription')->willReturn($description);
    }

    /**
     * @param array<string,string> $extraData
     */
    private function buildProduct(array $extraData = []): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Tote Bag');
        $product->method('getSku')->willReturn('TOTE-1');
        $product->method('getProductUrl')->willReturn('https://acme.test/tote');
        $product->method('isAvailable')->willReturn(true);
        $product->method('getFinalPrice')->willReturn(29.9);

        $data = $extraData + [
            'short_description' => 'A <b>roomy</b> tote.',
            'ean' => '4006381333931',
            'mpn' => 'ACME-TOTE-01',
        ];
        $product->method('getData')->willReturnCallback(
            static fn(string $key = '', $index = null) => $data[$key] ?? ''
        );
        $product->method('getCreatedAt')->willReturn($extraData['created_at'] ?? null);
        $product->method('getUpdatedAt')->willReturn($extraData['updated_at'] ?? null);

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

    public function testFallsBackToTheStoreBaseUrlWhenTheCurrentUrlIsNotAbsolute(): void
    {
        $this->configFlags[SchemaOrgConfig::WEBPAGE_ENABLED] = true;
        $this->stubPageTitleAndDescription('Home', '');

        $url = $this->createMock(UrlInterface::class);
        $url->method('getCurrentUrl')->willReturn('http:///');

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);

        $this->url = $url;
        $webPage = $this->findNode($this->providerFor('cms_index_index')->getCurrentPageNodes(), 'WebPage');

        $this->assertSame('https://acme.test/', $webPage['url']);
        $this->assertSame('https://acme.test/#webpage', $webPage['@id']);
    }

    public function testSkipsACategoryUrlThatIsNotAbsolute(): void
    {
        $this->configFlags[SchemaOrgConfig::WEBPAGE_ENABLED] = true;
        $this->stubPageTitleAndDescription('Bags', '');

        $category = $this->createMock(Category::class);
        $category->method('getUrl')->willReturn('/gear/bags.html');
        $category->method('getData')->willReturn(null);

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn(null);
        $this->catalogData->method('getCategory')->willReturn($category);

        $webPage = $this->findNode($this->providerFor('catalog_category_view')->getCurrentPageNodes(), 'CollectionPage');

        $this->assertSame('https://acme.test/current', $webPage['url']);
    }

    public function testDecodesHtmlEntitiesAndEncodedMarkupOutOfTheDescription(): void
    {
        $product = $this->buildProduct(
            ['short_description' => "Warm hoodie. &bull; Machine wash. &lt;b&gt;Bold&lt;/b&gt;\n&amp; dry."]
        );

        $this->logoPathResolver->method('getPath')->willReturn(null);
        $this->catalogData->method('getBreadcrumbPath')->willReturn([]);
        $this->catalogData->method('getProduct')->willReturn($product);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');

        $node = $this->findNode($this->provider->getCurrentPageNodes(), 'Product');

        $this->assertSame('Warm hoodie. • Machine wash. Bold & dry.', $node['description']);
    }
}
