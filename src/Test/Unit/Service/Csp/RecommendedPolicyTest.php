<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Csp;

use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Csp\Model\Policy\FlagPolicy;
use MageObsidian\ModernFrontend\Service\Csp\RecommendedPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecommendedPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(FetchPolicy::class)) {
            $this->markTestSkipped('Magento_Csp is not available in this runtime.');
        }
    }

    public function testIsOnUnlessAModuleTurnsItOff(): void
    {
        $this->assertTrue((new RecommendedPolicy())->isEnabled());
        $this->assertFalse((new RecommendedPolicy(false))->isEnabled());
    }

    public static function inlineDirectives(): array
    {
        return ['scripts' => ['script-src'], 'styles' => ['style-src'], 'default' => ['default-src']];
    }

    #[DataProvider('inlineDirectives')]
    public function testDropsInlineAndEvalAndKeepsEveryOtherSource(string $id): void
    {
        $hardened = $this->find((new RecommendedPolicy())->apply([$this->relaxed($id)], false), $id);

        $this->assertFalse($hardened->isInlineAllowed());
        $this->assertFalse($hardened->isEvalAllowed());
        $this->assertFalse($hardened->isNoneAllowed());
        $this->assertSame(['https://cdn.example.com'], $hardened->getHostSources());
        $this->assertSame(['data'], $hardened->getSchemeSources());
        $this->assertTrue($hardened->isSelfAllowed());
        $this->assertSame(['abc123'], $hardened->getNonceValues());
        $this->assertSame(['hash-value' => 'sha256'], $hardened->getHashes());
        $this->assertTrue($hardened->isDynamicAllowed());
        $this->assertTrue($hardened->areEventHandlersAllowed());
    }

    public function testDropsEvalFromEveryOtherDirectiveAndLeavesItsInlineAlone(): void
    {
        $hardened = $this->find((new RecommendedPolicy())->apply([$this->relaxed('worker-src')], false), 'worker-src');

        $this->assertFalse($hardened->isEvalAllowed());
        $this->assertTrue($hardened->isInlineAllowed());
        $this->assertSame(['https://cdn.example.com'], $hardened->getHostSources());
    }

    public function testClosesBaseUriAndObjectSrcReplacingWhatWasCollected(): void
    {
        $result = (new RecommendedPolicy())->apply([
            new FetchPolicy('base-uri', false, ['https://evil.example.com'], [], true),
            new FetchPolicy('object-src', false, ['https://plugins.example.com']),
        ], false);

        $baseUri = $this->find($result, 'base-uri');
        $this->assertTrue($baseUri->isSelfAllowed());
        $this->assertFalse($baseUri->isNoneAllowed());
        $this->assertSame([], $baseUri->getHostSources());
        $this->assertTrue($this->find($result, 'object-src')->isNoneAllowed());
        $this->assertSame(['base-uri', 'object-src'], $this->ids($result));
    }

    public function testAddsBaseUriAndObjectSrcWhenNothingDeclaredThem(): void
    {
        $result = (new RecommendedPolicy())->apply([new FetchPolicy('img-src', false, [], [], true)], false);

        $this->assertSame(['img-src', 'base-uri', 'object-src'], $this->ids($result));
    }

    public function testAPageThatKeepsInlineStylesKeepsThemOnlyInStyleSrc(): void
    {
        $result = (new RecommendedPolicy())->apply(
            [$this->relaxed('style-src'), $this->relaxed('script-src'), $this->relaxed('default-src')],
            true
        );

        $this->assertTrue($this->find($result, 'style-src')->isInlineAllowed());
        $this->assertFalse($this->find($result, 'style-src')->isEvalAllowed());
        $this->assertFalse($this->find($result, 'script-src')->isInlineAllowed());
        $this->assertFalse($this->find($result, 'default-src')->isInlineAllowed());
    }

    public function testAPolicyAlreadyHardenedIsReturnedAsItWas(): void
    {
        $scripts = new FetchPolicy('script-src', false, ['https://cdn.example.com'], [], true);

        $this->assertSame($scripts, $this->find((new RecommendedPolicy())->apply([$scripts], false), 'script-src'));
    }

    public function testLeavesPoliciesThatAreNotFetchPoliciesAlone(): void
    {
        $flag = new FlagPolicy('upgrade-insecure-requests');

        $this->assertContains($flag, (new RecommendedPolicy())->apply([$flag], false));
    }

    private function relaxed(string $id): FetchPolicy
    {
        return new FetchPolicy(
            $id,
            false,
            ['https://cdn.example.com'],
            ['data'],
            true,
            true,
            true,
            ['abc123'],
            ['hash-value' => 'sha256'],
            true,
            true
        );
    }

    private function find(array $policies, string $id): FetchPolicy
    {
        foreach ($policies as $policy) {
            if ($policy instanceof FetchPolicy && $policy->getId() === $id) {
                return $policy;
            }
        }
        $this->fail('No ' . $id . ' policy was returned.');
    }

    private function ids(array $policies): array
    {
        return array_map(static fn ($policy): string => $policy->getId(), $policies);
    }
}
