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
use MageObsidian\ModernFrontend\Service\Csp\InlineScriptPolicy;
use PHPUnit\Framework\TestCase;

class InlineScriptPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(ConfigCollector::class)) {
            $this->markTestSkipped('Magento_Csp is not available in this runtime.');
        }
    }

    public function testAPolicyThatDisallowsInlineScriptsIsReportedAsStrict(): void
    {
        $policy = $this->policyFor([
            new FetchPolicy('style-src', false, [], [], true, true),
            new FetchPolicy('script-src', false, [], [], true, false),
        ]);

        $this->assertFalse($policy->allowsInlineScripts());
    }

    public function testAPolicyThatAllowsInlineScriptsIsReportedAsPermissive(): void
    {
        $policy = $this->policyFor([new FetchPolicy('script-src', false, [], [], true, true)]);

        $this->assertTrue($policy->allowsInlineScripts());
    }

    public function testWithoutAScriptPolicyInlineScriptsAreAllowedAsThePlatformTreatsThem(): void
    {
        $policy = $this->policyFor([new FetchPolicy('style-src', false, [], [], true, false)]);

        $this->assertTrue($policy->allowsInlineScripts());
    }

    public function testThePoliciesAreCollectedOncePerRequest(): void
    {
        $collector = $this->createMock(ConfigCollector::class);
        $collector->expects($this->once())
            ->method('collect')
            ->willReturn([new FetchPolicy('script-src', false, [], [], true, false)]);
        $policy = new InlineScriptPolicy($collector);

        $policy->allowsInlineScripts();

        $this->assertFalse($policy->allowsInlineScripts());
    }

    private function policyFor(array $collected): InlineScriptPolicy
    {
        $collector = $this->createStub(ConfigCollector::class);
        $collector->method('collect')->willReturn($collected);

        return new InlineScriptPolicy($collector);
    }
}
