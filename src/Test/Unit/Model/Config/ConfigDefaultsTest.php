<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Config;

use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use PHPUnit\Framework\TestCase;

final class ConfigDefaultsTest extends TestCase
{
    private const string CONFIG_FILE = __DIR__ . '/../../../../etc/config.xml';

    public function testHmrShipsDisabled(): void
    {
        $config = simplexml_load_file(self::CONFIG_FILE);
        $value = $config->xpath('default/' . ConfigProvider::HMR_ENABLED);

        self::assertCount(1, $value);
        self::assertSame('0', trim((string)$value[0]));
    }

    public function testPaymentPagesLeaveTheirInlineStylesToMagento(): void
    {
        $config = simplexml_load_file(self::CONFIG_FILE);

        foreach (['storefront_checkout_index_index', 'storefront_multishipping_checkout_billing'] as $page) {
            self::assertSame([], $config->xpath('default/csp/policies/' . $page . '/styles/inline'), $page);
        }
    }

    public function testTheStorefrontInlinePoliciesAreLeftToMagento(): void
    {
        $config = simplexml_load_file(self::CONFIG_FILE);

        foreach (['scripts', 'styles'] as $directive) {
            self::assertSame(
                [],
                $config->xpath('default/csp/policies/storefront/' . $directive . '/inline'),
                $directive
            );
        }
    }
}
