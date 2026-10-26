<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Csp;

use Magento\Csp\Model\Collector\ConfigCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Csp\Model\Policy\FlagPolicy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use MageObsidian\ModernFrontend\Plugin\Csp\ApplyStrictInlinePolicy;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApplyStrictInlinePolicyTest extends TestCase
{
    private const array PAYMENT_PAGES = ['checkout_index_index', 'multishipping_checkout_billing'];

    protected function setUp(): void
    {
        if (!class_exists(ConfigCollector::class)) {
            $this->markTestSkipped('Magento_Csp is not available in this runtime.');
        }
    }

    public function testDisallowsInlineScriptsAndStylesUnderAnObsidianTheme(): void
    {
        $result = $this->collect(true, 'cms_index_index', [
            $this->fetch('script-src', inline: true),
            $this->fetch('style-src', inline: true),
        ]);

        $this->assertFalse($this->policy($result, 'script-src')->isInlineAllowed());
        $this->assertFalse($this->policy($result, 'style-src')->isInlineAllowed());
    }

    public function testLeavesEveryPolicyAsCollectedOutsideAnObsidianTheme(): void
    {
        $policies = [
            $this->fetch('script-src', inline: true),
            $this->fetch('style-src', inline: true),
        ];

        $this->assertSame($policies, $this->collect(false, 'cms_index_index', $policies));
    }

    public static function paymentPages(): array
    {
        return array_map(static fn (string $page): array => [$page], array_combine(self::PAYMENT_PAGES, self::PAYMENT_PAGES));
    }

    #[DataProvider('paymentPages')]
    public function testPaymentPagesKeepInlineStylesButNotInlineScripts(string $page): void
    {
        $result = $this->collect(true, $page, [
            $this->fetch('script-src', inline: true),
            $this->fetch('style-src', inline: false),
        ]);

        $this->assertFalse($this->policy($result, 'script-src')->isInlineAllowed());
        $this->assertTrue($this->policy($result, 'style-src')->isInlineAllowed());
    }

    #[DataProvider('paymentPages')]
    public function testAPaymentPageTheStoreConfiguredItselfKeepsItsOwnStylePolicy(string $page): void
    {
        $styles = $this->fetch('style-src', inline: false);

        $result = $this->collect(true, $page, [$styles], ['csp/policies/storefront_' . $page . '/styles/inline' => '0']);

        $this->assertSame($styles, $this->policy($result, 'style-src'));
    }

    public function testKeepsEverythingElseAboutThePolicy(): void
    {
        $original = new FetchPolicy(
            'script-src',
            false,
            ['https://js.example.com'],
            ['data'],
            true,
            true,
            true,
            ['abc123'],
            ['hash-value' => 'sha256'],
            true,
            true
        );

        $rebuilt = $this->policy($this->collect(true, 'cms_index_index', [$original]), 'script-src');

        $this->assertFalse($rebuilt->isInlineAllowed());
        $this->assertFalse($rebuilt->isNoneAllowed());
        $this->assertSame(['https://js.example.com'], $rebuilt->getHostSources());
        $this->assertSame(['data'], $rebuilt->getSchemeSources());
        $this->assertTrue($rebuilt->isSelfAllowed());
        $this->assertTrue($rebuilt->isEvalAllowed());
        $this->assertSame(['abc123'], $rebuilt->getNonceValues());
        $this->assertSame(['hash-value' => 'sha256'], $rebuilt->getHashes());
        $this->assertTrue($rebuilt->isDynamicAllowed());
        $this->assertTrue($rebuilt->areEventHandlersAllowed());
    }

    public function testLeavesOtherDirectivesAndPolicyTypesAlone(): void
    {
        $images = $this->fetch('img-src', inline: true);
        $flag = new FlagPolicy('upgrade-insecure-requests');

        $result = $this->collect(true, 'cms_index_index', [$images, $flag]);

        $this->assertSame([$images, $flag], $result);
    }

    public function testAPolicyAlreadyStrictIsReturnedAsItWas(): void
    {
        $scripts = $this->fetch('script-src', inline: false);

        $result = $this->collect(true, 'cms_index_index', [$scripts]);

        $this->assertSame($scripts, $this->policy($result, 'script-src'));
    }

    private function collect(bool $obsidianTheme, string $page, array $policies, array $config = []): array
    {
        $detector = $this->createStub(ObsidianThemeDetector::class);
        $detector->method('isActive')->willReturn($obsidianTheme);
        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn($page);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static fn (string $path) => $config[$path] ?? null);

        return (new ApplyStrictInlinePolicy($detector, $request, $scopeConfig, self::PAYMENT_PAGES))
            ->afterCollect($this->createStub(ConfigCollector::class), $policies);
    }

    private function fetch(string $id, bool $inline): FetchPolicy
    {
        return new FetchPolicy($id, false, [], [], true, $inline);
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
