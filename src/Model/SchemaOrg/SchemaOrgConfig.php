<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;

class SchemaOrgConfig
{
    public const string ORGANIZATION_LOGO = ConfigProvider::SEO_PATH . 'organization_logo';
    public const string ORGANIZATION_DESCRIPTION = ConfigProvider::SEO_PATH . 'organization_description';
    public const string ORGANIZATION_SAME_AS = ConfigProvider::SEO_PATH . 'organization_same_as';
    public const string ORGANIZATION_CONTACT_TYPE = ConfigProvider::SEO_PATH . 'organization_contact_type';
    public const string ORGANIZATION_ADDRESS_ENABLED = ConfigProvider::SEO_PATH . 'organization_address_enabled';
    public const string WEBPAGE_ENABLED = ConfigProvider::SEO_PATH . 'webpage_enabled';
    public const string PRODUCT_BRAND_ATTRIBUTE = ConfigProvider::SEO_PATH . 'product_brand_attribute';
    public const string PRODUCT_GTIN_ATTRIBUTE = ConfigProvider::SEO_PATH . 'product_gtin_attribute';
    public const string PRODUCT_MPN_ATTRIBUTE = ConfigProvider::SEO_PATH . 'product_mpn_attribute';
    public const string PRODUCT_CONDITION = ConfigProvider::SEO_PATH . 'product_condition';
    public const string PRODUCT_IMAGE_LIMIT = ConfigProvider::SEO_PATH . 'product_image_limit';
    public const string PRICE_VALID_UNTIL_DAYS = ConfigProvider::SEO_PATH . 'price_valid_until_days';

    public const string STORE_NAME = 'general/store_information/name';
    public const string STORE_PHONE = 'general/store_information/phone';
    public const string STORE_COUNTRY_ID = 'general/store_information/country_id';
    public const string STORE_REGION_ID = 'general/store_information/region_id';
    public const string STORE_POSTCODE = 'general/store_information/postcode';
    public const string STORE_CITY = 'general/store_information/city';
    public const string STORE_STREET_LINE1 = 'general/store_information/street_line1';
    public const string STORE_STREET_LINE2 = 'general/store_information/street_line2';
    public const string STORE_VAT_NUMBER = 'general/store_information/merchant_vat_number';
    public const string STORE_EMAIL = 'trans_email/ident_general/email';
    public const string LOCALE_CODE = 'general/locale/code';
    public const string DEFAULT_DESCRIPTION = 'design/head/default_description';

    public const string LOGO_MEDIA_PREFIX = 'mage_obsidian/seo/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getString(string $path, ?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string)$value);

        return $text !== '' ? $text : null;
    }

    public function getInt(string $path, ?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isSetFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getSameAs(?int $storeId = null): array
    {
        $raw = $this->getString(self::ORGANIZATION_SAME_AS, $storeId);
        if ($raw === null) {
            return [];
        }

        $uris = [];
        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $line) {
            $uri = trim($line);
            if ($uri !== '') {
                $uris[$uri] = true;
            }
        }

        return array_keys($uris);
    }

    public function getInLanguage(?int $storeId = null): ?string
    {
        $locale = $this->getString(self::LOCALE_CODE, $storeId);

        return $locale !== null ? str_replace('_', '-', $locale) : null;
    }
}
