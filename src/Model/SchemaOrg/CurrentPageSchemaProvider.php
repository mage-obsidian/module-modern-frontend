<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\BreadcrumbListBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\OrganizationBuilder;
use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\ProductBuilder;
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
    private const string STORE_NAME_PATH = 'general/store_information/name';
    private const string SEARCH_URL_SUFFIX = 'catalogsearch/result/?q={search_term_string}';
    private const string LOGO_URL_PREFIX = 'logo/';
    private const string PRODUCT_IMAGE_ID = 'product_base_image';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CatalogData $catalogData,
        private readonly ImageHelper $imageHelper,
        private readonly LogoPathResolver $logoPathResolver,
        private readonly OrganizationBuilder $organizationBuilder,
        private readonly WebSiteBuilder $webSiteBuilder,
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
        $baseUrl = $store->getBaseUrl();
        $siteName = $this->resolveSiteName($store);

        $nodes = [
            $this->organizationBuilder->build($siteName, $baseUrl, $this->resolveLogoUrl($store)),
            $this->webSiteBuilder->build($siteName, $baseUrl, $baseUrl . self::SEARCH_URL_SUFFIX),
            $this->breadcrumbListBuilder->build($this->resolveBreadcrumbs($baseUrl)),
        ];

        $product = $this->catalogData->getProduct();
        if ($product instanceof ProductInterface) {
            $nodes[] = $this->productBuilder->build($this->extractProductData($product, $store));
        }

        // Builders return [] for "nothing to emit" (e.g. no breadcrumbs); drop those.
        return array_values(array_filter($nodes, static fn(array $node): bool => $node !== []));
    }

    private function resolveSiteName(Store $store): string
    {
        $configured = (string)$this->scopeConfig->getValue(
            self::STORE_NAME_PATH,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        return $configured !== '' ? $configured : (string)$store->getFrontendName();
    }

    private function resolveLogoUrl(Store $store): ?string
    {
        $path = $this->logoPathResolver->getPath();
        if ($path === null || $path === '') {
            return null;
        }

        return $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . self::LOGO_URL_PREFIX . $path;
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

    /**
     * @return array<string,mixed>
     */
    private function extractProductData(ProductInterface $product, Store $store): array
    {
        return [
            'name' => (string)$product->getName(),
            'url' => method_exists($product, 'getProductUrl') ? (string)$product->getProductUrl() : null,
            'sku' => (string)$product->getSku(),
            'description' => $this->resolveDescription($product),
            'image' => $this->resolveProductImageUrl($product),
            'price' => $this->resolvePrice($product),
            'priceCurrency' => $store->getCurrentCurrencyCode(),
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
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($raw)) ?? '');

        return $text !== '' ? $text : null;
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
}
