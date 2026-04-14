<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\ViewModel;

use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Model\SchemaOrg\CurrentPageSchemaProvider;
use MageObsidian\ModernFrontend\Model\SchemaOrg\JsonLdRenderer;
use MageObsidian\ModernFrontend\ViewModel\SchemaOrg;
use PHPUnit\Framework\TestCase;

/**
 * Loads ConfigProvider (which implements a Magento interface), so it runs in
 * the full suite (phpunit.xml), excluded from the standalone CI suite.
 */
class SchemaOrgTest extends TestCase
{
    public function testIsEnabledDelegatesToConfigProvider(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isStructuredDataEnabled')->willReturn(false);

        $viewModel = new SchemaOrg(
            $this->createMock(CurrentPageSchemaProvider::class),
            new JsonLdRenderer(),
            $configProvider
        );

        $this->assertFalse($viewModel->isEnabled());
    }

    public function testGetCurrentPageJsonLdRendersProviderNodes(): void
    {
        $provider = $this->createMock(CurrentPageSchemaProvider::class);
        $provider->method('getCurrentPageNodes')->willReturn([
            ['@type' => 'Organization', 'name' => 'Acme'],
        ]);

        $viewModel = new SchemaOrg($provider, new JsonLdRenderer(), $this->createMock(ConfigProvider::class));

        $this->assertSame(
            '<script type="application/ld+json">'
            . '{"@context":"https://schema.org","@type":"Organization","name":"Acme"}'
            . '</script>',
            $viewModel->getCurrentPageJsonLd()
        );
    }

    public function testRenderJsonLdWrapsCustomType(): void
    {
        $viewModel = new SchemaOrg(
            $this->createMock(CurrentPageSchemaProvider::class),
            new JsonLdRenderer(),
            $this->createMock(ConfigProvider::class)
        );

        $html = $viewModel->renderJsonLd('FAQPage', ['mainEntity' => []]);

        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('"@context":"https://schema.org"', $html);
    }
}
