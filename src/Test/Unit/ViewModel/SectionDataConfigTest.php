<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\ModernFrontend\ViewModel\SectionDataConfig;
use PHPUnit\Framework\TestCase;

class SectionDataConfigTest extends TestCase
{
    public function testConvertsAdminMinutesToSecondsAndCarriesExpirableSections(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('customer/online_customers/section_data_lifetime', ScopeInterface::SCOPE_STORE)
            ->willReturn('60');

        $config = (new SectionDataConfig($scopeConfig))->getConfig();

        $this->assertSame(3600, $config['lifetime']);
        $this->assertSame(['cart'], $config['expirable']);
    }

    public function testDisablesBackstopWhenLifetimeIsUnsetOrNonPositive(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame(0, (new SectionDataConfig($scopeConfig))->getConfig()['lifetime']);
    }
}
