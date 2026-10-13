<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Csp;

use Magento\Csp\Model\Collector\ConfigCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use MageObsidian\ModernFrontend\Service\Csp\InlineStylePolicy;
use PHPUnit\Framework\TestCase;

class InlineStylePolicyTest extends TestCase
{
    public function testAPageWhosePolicyDisallowsInlineStylesIsReportedAsStrict(): void
    {
        $policy = $this->policyFor([
            new FetchPolicy('script-src', false, [], [], true, true),
            new FetchPolicy('style-src', false, [], [], true, false),
        ]);

        $this->assertFalse($policy->allowsInlineStyles());
    }

    public function testAPaymentPageThatKeepsInlineStylesIsReportedAsPermissive(): void
    {
        $policy = $this->policyFor([new FetchPolicy('style-src', false, [], [], true, true)]);

        $this->assertTrue($policy->allowsInlineStyles());
    }

    public function testWithoutAStylePolicyInlineStylesAreAllowedAsThePlatformTreatsThem(): void
    {
        $policy = $this->policyFor([new FetchPolicy('script-src', false, [], [], true, false)]);

        $this->assertTrue($policy->allowsInlineStyles());
    }

    public function testThePoliciesAreCollectedOncePerRequest(): void
    {
        $collector = $this->createMock(ConfigCollector::class);
        $collector->expects($this->once())
            ->method('collect')
            ->willReturn([new FetchPolicy('style-src', false, [], [], true, false)]);
        $policy = new InlineStylePolicy($collector);

        $policy->allowsInlineStyles();

        $this->assertFalse($policy->allowsInlineStyles());
    }

    private function policyFor(array $collected): InlineStylePolicy
    {
        $collector = $this->createStub(ConfigCollector::class);
        $collector->method('collect')->willReturn($collected);

        return new InlineStylePolicy($collector);
    }
}
