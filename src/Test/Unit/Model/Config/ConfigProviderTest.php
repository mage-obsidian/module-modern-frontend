<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Config;

use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Model\Config\Source\CheckoutLayout;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

/**
 * Mocks Magento framework config/state types, so it runs in a Magento root (not
 * the standalone CI suite).
 */
class ConfigProviderTest extends TestCase
{
    public function testCheckoutLayoutModeReturnsTheConfiguredValue(): void
    {
        $provider = $this->provider('onepage');
        $this->assertSame('onepage', $provider->getCheckoutLayoutMode());
    }

    public function testCheckoutLayoutModeFallsBackToStepped(): void
    {
        $provider = $this->provider('');
        $this->assertSame(CheckoutLayout::STEPPED, $provider->getCheckoutLayoutMode());
    }

    private function provider(?string $storedValue): ConfigProvider
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with(ConfigProvider::CHECKOUT_LAYOUT_MODE, ScopeInterface::SCOPE_STORE)
            ->willReturn($storedValue);

        return new ConfigProvider($scopeConfig, $this->createMock(State::class));
    }
}
