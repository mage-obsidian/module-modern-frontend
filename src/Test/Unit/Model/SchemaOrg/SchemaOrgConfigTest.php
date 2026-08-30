<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\ModernFrontend\Model\SchemaOrg\SchemaOrgConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Mocks a Magento framework type, so it runs in the full suite (phpunit.xml),
 * excluded from the standalone CI suite.
 */
class SchemaOrgConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private SchemaOrgConfig $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new SchemaOrgConfig($this->scopeConfig);
    }

    public function testReadsStringsAtStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(SchemaOrgConfig::ORGANIZATION_CONTACT_TYPE, ScopeInterface::SCOPE_STORE, 3)
            ->willReturn('  sales  ');

        $this->assertSame('sales', $this->config->getString(SchemaOrgConfig::ORGANIZATION_CONTACT_TYPE, 3));
    }

    public function testTreatsBlankAndNonScalarValuesAsAbsent(): void
    {
        $this->scopeConfig->method('getValue')->willReturnOnConsecutiveCalls('', '   ', null, ['a']);

        for ($i = 0; $i < 4; $i++) {
            $this->assertNull($this->config->getString(SchemaOrgConfig::ORGANIZATION_DESCRIPTION));
        }
    }

    public function testSplitsSameAsOnNewlinesAndCommasAndDeduplicates(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(
            "https://x.com/acme\n https://www.linkedin.com/company/acme ,https://x.com/acme\n\n"
        );

        $this->assertSame(
            ['https://x.com/acme', 'https://www.linkedin.com/company/acme'],
            $this->config->getSameAs()
        );
    }

    public function testSameAsIsEmptyWhenUnconfigured(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame([], $this->config->getSameAs());
    }

    public function testConvertsMagentoLocaleToABcp47Tag(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('es_AR');

        $this->assertSame('es-AR', $this->config->getInLanguage());
    }

    public function testInLanguageIsNullWithoutALocale(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->assertNull($this->config->getInLanguage());
    }

    public function testCastsIntegersAndFlags(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('365');
        $this->scopeConfig->method('isSetFlag')->willReturn(true);

        $this->assertSame(365, $this->config->getInt(SchemaOrgConfig::PRICE_VALID_UNTIL_DAYS));
        $this->assertTrue($this->config->isSetFlag(SchemaOrgConfig::WEBPAGE_ENABLED));
    }

    public function testSeoPathsLiveUnderTheModuleNamespace(): void
    {
        $this->assertSame('mage_obsidian/seo/organization_logo', SchemaOrgConfig::ORGANIZATION_LOGO);
        $this->assertSame('mage_obsidian/seo/organization_same_as', SchemaOrgConfig::ORGANIZATION_SAME_AS);
        $this->assertSame('mage_obsidian/seo/webpage_enabled', SchemaOrgConfig::WEBPAGE_ENABLED);
        $this->assertSame('mage_obsidian/seo/product_gtin_attribute', SchemaOrgConfig::PRODUCT_GTIN_ATTRIBUTE);
    }
}
