<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Csp;

use MageObsidian\ModernFrontend\Service\Csp\PaymentPages;
use PHPUnit\Framework\TestCase;

final class PaymentPagesDiTest extends TestCase
{
    private const string FRONTEND_DI = __DIR__ . '/../../../../etc/frontend/di.xml';

    public function testTheFallbackListsTheStorefrontPaymentPagesTheCoreDeclares(): void
    {
        $config = simplexml_load_file(self::FRONTEND_DI);
        $items = $config->xpath(sprintf(
            'type[@name="%s"]/arguments/argument[@name="fallbackActions"]/item',
            PaymentPages::class
        ));

        self::assertSame(
            ['checkout_index_index', 'checkout_onepage_success', 'multishipping_checkout_billing'],
            array_map(static fn (\SimpleXMLElement $item): string => trim((string)$item), $items)
        );
    }
}
