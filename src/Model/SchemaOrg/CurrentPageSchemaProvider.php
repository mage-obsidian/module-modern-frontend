<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg;

use DateTime;
use DateTimeZone;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\FinalPriceInterface;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\BreadcrumbListBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\OrganizationBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\ProductBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\WebPageBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\WebSiteBuilder;
use Throwable;

/**
 * The Magento-facing adapter: gathers data for the current request from
 * Magento services and feeds the pure builders. Everything Magento-specific
 * lives here so the builders stay pure and unit-testable.
 *
 * Returns the schema.org nodes appropriate to the current page: Organization
 * and WebSite are always present (site-wide); BreadcrumbList and Product are
 * added only when the page carries that context.
 */
class CurrentPageSchemaProvider
{
    private const string SEARCH_URL_SUFFIX = 'catalogsearch/result/?q={search_term_string}';
    private const string PRODUCT_IMAGE_ID = 'product_base_image';
    private const string ORGANIZATION_FRAGMENT = '#organization';
    private const string WEBSITE_FRAGMENT = '#website';
    private const string BREADCRUMB_FRAGMENT = '#breadcrumb';
    private const string PRODUCT_FRAGMENT = '#product';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly SchemaOrgConfig $config,
        private readonly CatalogData $catalogData,
        private readonly ImageHelper $imageHelper,
        private readonly LogoPathResolver $logoPathResolver,
        private readonly PageConfig $pageConfig,
        private readonly HttpRequest $request,
        private readonly CmsPage $cmsPage,
        private readonly TimezoneInterface $timezone,
        private readonly UrlInterface $url,
        private readonly RegionFactory $regionFactory,
        private readonly PageTypeResolver $pageTypeResolver,
        private readonly OrganizationBuilder $organizationBuilder,
        private readonly WebSiteBuilder $webSiteBuilder,
        private readonly WebPageBuilder $webPageBuilder,
        private readonly BreadcrumbListBuilder $breadcrumbListBuilder,
        private readonly ProductBuilder $productBuilder
    ) {
    }

    /**
     * @return list<array<string,mixed>> Non-empty schema.org nodes for the current page.
     */
    public function getCurrentPageNodes(): array
    {
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        $baseUrl = $store->getBaseUrl();
        $siteName = $this->resolveSiteName($store);
        $organizationId = $baseUrl . self::ORGANIZATION_FRAGMENT;
        $webSiteId = $baseUrl . self::WEBSITE_FRAGMENT;

        $product = $this->catalogData->getProduct();
        $product = $product instanceof ProductInterface ? $product : null;
        $pageUrl = $this->resolvePageUrl($product, $baseUrl);

        $productNode = $product === null
            ? []
            : $this->productBuilder->build(
                $this->extractProductData($product, $store, $pageUrl . self::PRODUCT_FRAGMENT)
            );
        $productId = $productNode['@id'] ?? null;

        $breadcrumbNode = $this->breadcrumbListBuilder->build(
            $this->resolveBreadcrumbs($baseUrl),
            $pageUrl . self::BREADCRUMB_FRAGMENT
        );
        $breadcrumbId = $breadcrumbNode['@id'] ?? null;

        $nodes = [
            $this->organizationBuilder->build(
                $this->extractOrganizationData($store, $siteName, $baseUrl, $organizationId)
            ),
            $this->webSiteBuilder->build([
                '@id' => $webSiteId,
                'name' => $siteName,
                'url' => $baseUrl,
                'inLanguage' => $this->config->getInLanguage($storeId),
                'publisherId' => $organizationId,
                'searchUrlTemplate' => $baseUrl . self::SEARCH_URL_SUFFIX,
            ]),
            $this->buildWebPageNode($storeId, $pageUrl, $webSiteId, $organizationId, $breadcrumbId, $productId, $product),
            $breadcrumbNode,
            $productNode,
        ];

        // Builders return [] for "nothing to emit" (e.g. no breadcrumbs); drop those.
        return array_values(array_filter($nodes, static fn(array $node): bool => $node !== []));
    }

    private function buildWebPageNode(
        int $storeId,
        string $pageUrl,
        string $webSiteId,
        string $organizationId,
        ?string $breadcrumbId,
        ?string $productId,
        ?ProductInterface $product
    ): array {
        if (!$this->config->isSetFlag(SchemaOrgConfig::WEBPAGE_ENABLED, $storeId)) {
            return [];
        }

        [$datePublished, $dateModified] = $this->resolvePageDates($product);

        return $this->webPageBuilder->build([
            'type' => $this->pageTypeResolver->resolve((string)$this->request->getFullActionName()),
            'url' => $pageUrl,
            'name' => $this->resolvePageName(),
            'description' => $this->resolvePageDescription($storeId),
            'inLanguage' => $this->config->getInLanguage($storeId),
            'isPartOfId' => $webSiteId,
            'publisherId' => $organizationId,
            'breadcrumbId' => $breadcrumbId,
            'mainEntityId' => $productId,
            'primaryImageOfPage' => $product !== null ? $this->resolveProductImageUrl($product) : null,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
        ]);
    }

    private function resolveSiteName(Store $store): string
    {
        $configured = $this->config->getString(SchemaOrgConfig::STORE_NAME, (int)$store->getId());

        return $configured ?? (string)$store->getFrontendName();
    }

    /**
     * @return array<string,mixed>
     */
    private function extractOrganizationData(Store $store, string $siteName, string $baseUrl, string $id): array
    {
        $storeId = (int)$store->getId();

        $data = [
            '@id' => $id,
            'name' => $siteName,
            'url' => $baseUrl,
            'logo' => $this->resolveLogoUrl($store),
            'description' => $this->config->getString(SchemaOrgConfig::ORGANIZATION_DESCRIPTION, $storeId)
                ?? $this->config->getString(SchemaOrgConfig::DEFAULT_DESCRIPTION, $storeId),
            'telephone' => $this->config->getString(SchemaOrgConfig::STORE_PHONE, $storeId),
            'email' => $this->config->getString(SchemaOrgConfig::STORE_EMAIL, $storeId),
            'vatID' => $this->config->getString(SchemaOrgConfig::STORE_VAT_NUMBER, $storeId),
            'contactType' => $this->config->getString(SchemaOrgConfig::ORGANIZATION_CONTACT_TYPE, $storeId),
            'sameAs' => $this->config->getSameAs($storeId),
        ];

        if ($this->config->isSetFlag(SchemaOrgConfig::ORGANIZATION_ADDRESS_ENABLED, $storeId)) {
            $data['address'] = $this->resolveAddress($storeId);
        }

        return $data;
    }

    /**
     * @return array<string,string|null>
     */
    private function resolveAddress(int $storeId): array
    {
        $street = [];
        foreach ([SchemaOrgConfig::STORE_STREET_LINE1, SchemaOrgConfig::STORE_STREET_LINE2] as $path) {
            $line = $this->config->getString($path, $storeId);
            if ($line !== null) {
                $street[] = $line;
            }
        }

        return [
            'streetAddress' => $street === [] ? null : implode(', ', $street),
            'addressLocality' => $this->config->getString(SchemaOrgConfig::STORE_CITY, $storeId),
            'addressRegion' => $this->resolveRegionName($storeId),
            'postalCode' => $this->config->getString(SchemaOrgConfig::STORE_POSTCODE, $storeId),
            'addressCountry' => $this->config->getString(SchemaOrgConfig::STORE_COUNTRY_ID, $storeId),
        ];
    }

    private function resolveRegionName(int $storeId): ?string
    {
        $region = $this->config->getString(SchemaOrgConfig::STORE_REGION_ID, $storeId);
        if ($region === null || !ctype_digit($region)) {
            return $region;
        }

        try {
            $name = (string)$this->regionFactory->create()->load((int)$region)->getName();
        } catch (Throwable) {
            return null;
        }

        return $name !== '' ? $name : null;
    }

    private function resolveLogoUrl(Store $store): ?string
    {
        $mediaUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        $configured = $this->config->getString(SchemaOrgConfig::ORGANIZATION_LOGO, (int)$store->getId());
        if ($configured !== null) {
            return preg_match('#^https?://#i', $configured) === 1
                ? $configured
                : $mediaUrl . SchemaOrgConfig::LOGO_MEDIA_PREFIX . ltrim($configured, '/');
        }

        $path = ltrim((string)$this->logoPathResolver->getPath(), '/');
        if ($path === '' || str_ends_with($path, '/')) {
            return null;
        }

        return $mediaUrl . $path;
    }

    /**
     * Maps Magento's breadcrumb path (label/link) to the builder's shape and
     * prepends Home, mirroring the native breadcrumbs block.
     *
     * @return list<array{name:string, url:?string}>
     */
    private function resolveBreadcrumbs(string $baseUrl): array
    {
        $path = $this->catalogData->getBreadcrumbPath();
        if (!is_array($path) || $path === []) {
            return [];
        }

        $crumbs = [['name' => (string)__('Home'), 'url' => $baseUrl]];
        foreach ($path as $crumb) {
            $crumbs[] = [
                'name' => (string)($crumb['label'] ?? ''),
                'url' => isset($crumb['link']) && $crumb['link'] !== '' ? (string)$crumb['link'] : null,
            ];
        }

        return $crumbs;
    }

    private function resolvePageUrl(?ProductInterface $product, string $baseUrl): string
    {
        if ($product !== null && method_exists($product, 'getProductUrl')) {
            $url = $this->absoluteUrl((string)$product->getProductUrl());
            if ($url !== null) {
                return $url;
            }
        }

        $category = $this->catalogData->getCategory();
        if ($category !== null && method_exists($category, 'getUrl')) {
            $url = $this->absoluteUrl((string)$category->getUrl());
            if ($url !== null) {
                return $url;
            }
        }

        try {
            $url = $this->absoluteUrl((string)$this->url->getCurrentUrl());
        } catch (Throwable) {
            $url = null;
        }

        return $url ?? $baseUrl;
    }

    private function absoluteUrl(string $url): ?string
    {
        $url = trim($url);

        return preg_match('#^https?://[^/?\#]+#i', $url) === 1 ? $url : null;
    }

    private function resolvePageName(): ?string
    {
        try {
            $title = $this->pageConfig->getTitle();
        } catch (Throwable) {
            return null;
        }

        $short = trim((string)$title->getShort());

        return $short !== '' ? $short : (trim((string)$title->get()) ?: null);
    }

    private function resolvePageDescription(int $storeId): ?string
    {
        try {
            $description = trim((string)$this->pageConfig->getDescription());
        } catch (Throwable) {
            $description = '';
        }

        return $description !== ''
            ? $description
            : $this->config->getString(SchemaOrgConfig::DEFAULT_DESCRIPTION, $storeId);
    }

    /**
     * @return array{0:?DateTime, 1:?DateTime}
     */
    private function resolvePageDates(?ProductInterface $product): array
    {
        if ($product !== null) {
            return [
                $this->toStoreDate($product->getCreatedAt()),
                $this->toStoreDate($product->getUpdatedAt()),
            ];
        }

        $category = $this->catalogData->getCategory();
        if ($category !== null && method_exists($category, 'getData')) {
            return [
                $this->toStoreDate($category->getData('created_at')),
                $this->toStoreDate($category->getData('updated_at')),
            ];
        }

        if ((int)$this->cmsPage->getId() > 0) {
            return [
                $this->toStoreDate($this->cmsPage->getCreationTime()),
                $this->toStoreDate($this->cmsPage->getUpdateTime()),
            ];
        }

        return [null, null];
    }

    private function toStoreDate(mixed $raw): ?DateTime
    {
        if (!is_scalar($raw)) {
            return null;
        }

        $value = trim((string)$raw);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return $this->timezone->date(new DateTime($value, new DateTimeZone('UTC')));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function extractProductData(ProductInterface $product, Store $store, string $id): array
    {
        $storeId = (int)$store->getId();
        [$lowPrice, $highPrice] = $this->resolvePriceRange($product);

        return [
            '@id' => $id,
            'name' => (string)$product->getName(),
            'url' => method_exists($product, 'getProductUrl') ? (string)$product->getProductUrl() : null,
            'sku' => (string)$product->getSku(),
            'description' => $this->resolveDescription($product),
            'image' => $this->resolveProductImages($product, $storeId),
            'brand' => $this->resolveAttributeValue($product, SchemaOrgConfig::PRODUCT_BRAND_ATTRIBUTE, $storeId),
            'gtin' => $this->resolveAttributeValue($product, SchemaOrgConfig::PRODUCT_GTIN_ATTRIBUTE, $storeId),
            'mpn' => $this->resolveAttributeValue($product, SchemaOrgConfig::PRODUCT_MPN_ATTRIBUTE, $storeId),
            'price' => $this->resolvePrice($product),
            'lowPrice' => $lowPrice,
            'highPrice' => $highPrice,
            'offerCount' => $lowPrice !== null && $highPrice !== null && $lowPrice !== $highPrice
                ? $this->resolveOfferCount($product)
                : null,
            'priceCurrency' => $store->getCurrentCurrencyCode(),
            'priceValidUntil' => $this->resolvePriceValidUntil($product, $storeId),
            'itemCondition' => $this->config->getString(SchemaOrgConfig::PRODUCT_CONDITION, $storeId),
            'availability' => method_exists($product, 'isAvailable') && $product->isAvailable()
                ? 'InStock'
                : 'OutOfStock',
        ];
    }

    private function resolveDescription(ProductInterface $product): ?string
    {
        $raw = (string)($product->getData('short_description') ?: $product->getData('description'));
        if ($raw === '') {
            return null;
        }

        // schema.org description is plain text; strip markup and collapse whitespace.
        $decoded = strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim(preg_replace('/\s+/', ' ', $decoded) ?? '');

        return $text !== '' ? $text : null;
    }

    private function resolveAttributeValue(ProductInterface $product, string $configPath, int $storeId): ?string
    {
        $code = $this->config->getString($configPath, $storeId);
        if ($code === null) {
            return null;
        }

        if (method_exists($product, 'getAttributeText')) {
            try {
                $text = $product->getAttributeText($code);
            } catch (Throwable) {
                $text = null;
            }
            if (is_array($text)) {
                $text = implode(', ', array_map(static fn($item): string => (string)$item, $text));
            }
            if ($text !== null && $text !== false && trim((string)$text) !== '') {
                return trim((string)$text);
            }
        }

        $raw = $product->getData($code);

        return is_scalar($raw) && trim((string)$raw) !== '' ? trim((string)$raw) : null;
    }

    /**
     * @return list<string>
     */
    private function resolveProductImages(ProductInterface $product, int $storeId): array
    {
        $limit = max(1, $this->config->getInt(SchemaOrgConfig::PRODUCT_IMAGE_LIMIT, $storeId));

        $urls = [];
        try {
            $gallery = method_exists($product, 'getMediaGalleryImages') ? $product->getMediaGalleryImages() : null;
            foreach ($gallery ?? [] as $image) {
                $url = method_exists($image, 'getUrl') ? trim((string)$image->getUrl()) : '';
                if ($url !== '') {
                    $urls[$url] = true;
                }
                if (count($urls) >= $limit) {
                    break;
                }
            }
        } catch (Throwable) {
            $urls = [];
        }

        if ($urls !== []) {
            return array_keys($urls);
        }

        $base = $this->resolveProductImageUrl($product);

        return $base !== null ? [$base] : [];
    }

    /**
     * The catalog image helper falls back to a placeholder and may throw when a
     * theme lacks the image id; never let that void the rest of the page schema.
     */
    private function resolveProductImageUrl(ProductInterface $product): ?string
    {
        try {
            $url = (string)$this->imageHelper->init($product, self::PRODUCT_IMAGE_ID)->getUrl();
        } catch (Throwable) {
            return null;
        }

        return $url !== '' ? $url : null;
    }

    private function resolvePrice(ProductInterface $product): ?float
    {
        $price = method_exists($product, 'getFinalPrice') ? $product->getFinalPrice() : null;
        if ($price === null) {
            $price = $product->getPrice();
        }

        return $price === null ? null : (float)$price;
    }

    /**
     * @return array{0:?float, 1:?float}
     */
    private function resolveOfferCount(ProductInterface $product): ?int
    {
        if (!method_exists($product, 'getTypeInstance')) {
            return null;
        }

        try {
            $childrenIds = $product->getTypeInstance()->getChildrenIds((int)$product->getId());
        } catch (Throwable) {
            return null;
        }

        if (!is_array($childrenIds)) {
            return null;
        }

        $ids = [];
        foreach ($childrenIds as $group) {
            foreach (is_array($group) ? $group : [$group] as $id) {
                $ids[(int)$id] = true;
            }
        }

        return $ids === [] ? null : count($ids);
    }

    private function resolvePriceRange(ProductInterface $product): array
    {
        if (!method_exists($product, 'getPriceInfo')) {
            return [null, null];
        }

        try {
            $finalPrice = $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE);
            if (!$finalPrice instanceof FinalPriceInterface) {
                return [null, null];
            }

            $minimal = $finalPrice->getMinimalPrice();
            $maximal = $finalPrice->getMaximalPrice();
        } catch (Throwable) {
            return [null, null];
        }

        return [
            $minimal !== null ? (float)$minimal->getValue() : null,
            $maximal !== null ? (float)$maximal->getValue() : null,
        ];
    }

    /**
     * A special price already expired must never be advertised as the date the
     * price is valid until; Google reads that as a stale offer.
     */
    private function resolvePriceValidUntil(ProductInterface $product, int $storeId): ?string
    {
        $now = $this->timezone->date();
        $specialTo = $this->toStoreDate($product->getData('special_to_date'));
        if ($specialTo !== null && $specialTo > $now) {
            return $specialTo->format('Y-m-d');
        }

        $days = $this->config->getInt(SchemaOrgConfig::PRICE_VALID_UNTIL_DAYS, $storeId);
        if ($days <= 0) {
            return null;
        }

        return (clone $now)->modify(sprintf('+%d days', $days))->format('Y-m-d');
    }
}
