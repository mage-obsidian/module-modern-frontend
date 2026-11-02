<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Csp;

use Magento\Csp\Model\CompositePolicyCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use MageObsidian\ModernFrontend\Plugin\Csp\ApplyRecommendedPolicy;
use MageObsidian\ModernFrontend\Service\Csp\InlineStylePolicy;
use MageObsidian\ModernFrontend\Service\Csp\RecommendedPolicy;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use PHPUnit\Framework\TestCase;

class ApplyRecommendedPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(CompositePolicyCollector::class)) {
            $this->markTestSkipped('Magento_Csp is not available in this runtime.');
        }
    }

    public function testAnInlinePermissionAddedDuringTheRenderDoesNotSurvive(): void
    {
        $merged = new FetchPolicy('script-src', false, ['https://cdn.example.com'], [], true, true, false, [], [
            'aGFzaA==' => 'sha256',
        ]);

        $scripts = $this->policy($this->collect([$merged]), 'script-src');

        $this->assertFalse($scripts->isInlineAllowed());
        $this->assertSame(['aGFzaA==' => 'sha256'], $scripts->getHashes());
        $this->assertSame(['https://cdn.example.com'], $scripts->getHostSources());
    }

    public function testClosesBaseUriAndObjectSrc(): void
    {
        $result = $this->collect([new FetchPolicy('img-src', false, [], [], true)]);

        $this->assertTrue($this->policy($result, 'base-uri')->isSelfAllowed());
        $this->assertTrue($this->policy($result, 'object-src')->isNoneAllowed());
    }

    public function testKeepsInlineStylesOnAPageTheCodeDeclaredForThem(): void
    {
        $styles = new FetchPolicy('style-src', false, [], [], true, true);

        $result = $this->collect([$styles], inlineStyles: true);

        $this->assertTrue($this->policy($result, 'style-src')->isInlineAllowed());
    }

    public function testLeavesThePolicyAsCollectedWhenAModuleTurnedItOff(): void
    {
        $policies = [new FetchPolicy('script-src', false, [], [], true, true, true)];

        $this->assertSame($policies, $this->collect($policies, enabled: false));
    }

    public function testLeavesThePolicyAsCollectedOutsideAnObsidianTheme(): void
    {
        $policies = [new FetchPolicy('script-src', false, [], [], true, true, true)];

        $this->assertSame($policies, $this->collect($policies, obsidianTheme: false));
    }

    private function collect(
        array $policies,
        bool $enabled = true,
        bool $obsidianTheme = true,
        bool $inlineStyles = false
    ): array {
        $detector = $this->createStub(ObsidianThemeDetector::class);
        $detector->method('isActive')->willReturn($obsidianTheme);
        $inlineStylePolicy = $this->createStub(InlineStylePolicy::class);
        $inlineStylePolicy->method('allowsInlineStyles')->willReturn($inlineStyles);

        return (new ApplyRecommendedPolicy(new RecommendedPolicy($enabled), $detector, $inlineStylePolicy))
            ->afterCollect($this->createStub(CompositePolicyCollector::class), $policies);
    }

    private function policy(array $policies, string $id): FetchPolicy
    {
        foreach ($policies as $policy) {
            if ($policy instanceof FetchPolicy && $policy->getId() === $id) {
                return $policy;
            }
        }
        $this->fail('No ' . $id . ' policy was returned.');
    }
}
