<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Config\Source;

use MageObsidian\ModernFrontend\Model\Config\Source\CheckoutLayout;
use PHPUnit\Framework\TestCase;

/**
 * toOptionArray() builds Phrase labels via __(), so it needs the Magento
 * framework (runs in a Magento root, not the standalone CI suite).
 */
class CheckoutLayoutTest extends TestCase
{
    public function testExposesBothLayoutValues(): void
    {
        $values = array_column((new CheckoutLayout())->toOptionArray(), 'value');

        $this->assertSame([CheckoutLayout::STEPPED, CheckoutLayout::ONEPAGE], $values);
    }
}
