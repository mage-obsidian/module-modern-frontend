<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Csp;

use InvalidArgumentException;
use Magento\Csp\Api\Data\PolicyInterface;
use Magento\Csp\Helper\InlineUtil;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\View\Helper\SecureHtmlRender\TagData;
use MageObsidian\ModernFrontend\Plugin\Csp\AuthorizeCacheableScriptsByHash;
use MageObsidian\ModernFrontend\Service\Csp\InlineScriptPolicy;
use MageObsidian\ModernFrontend\Service\Csp\ResponseCacheability;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use PHPUnit\Framework\TestCase;

class AuthorizeCacheableScriptsByHashTest extends TestCase
{
    private const string SCRIPT = 'window.answer = 42;';

    private array $collected = [];

    private array $proceeded = [];

    protected function setUp(): void
    {
        if (!class_exists(InlineUtil::class)) {
            $this->markTestSkipped('Magento_Csp is not available in this runtime.');
        }
        $this->collected = [];
        $this->proceeded = [];
    }

    public function testAScriptOnACacheableResponseIsAuthorizedByTheHashOfItsContent(): void
    {
        $tag = new TagData('script', [], self::SCRIPT, false);

        $result = $this->plugin()->aroundProcessTag($this->createStub(InlineUtil::class), $this->proceed(), $tag);

        $this->assertSame([], $this->proceeded);
        $this->assertArrayNotHasKey('nonce', $result->getAttributes());
        $this->assertCount(1, $this->collected);
        $policy = $this->collected[0];
        $this->assertInstanceOf(FetchPolicy::class, $policy);
        $this->assertSame('script-src', $policy->getId());
        $this->assertSame([base64_encode(hash('sha256', self::SCRIPT, true)) => 'sha256'], $policy->getHashes());
        $this->assertSame([], $policy->getNonceValues());
    }

    public function testAScriptOnAResponseThatIsNotStoredGoesToThePlatform(): void
    {
        $tag = new TagData('script', [], self::SCRIPT, false);

        $result = $this->plugin(storable: false)
            ->aroundProcessTag($this->createStub(InlineUtil::class), $this->proceed(), $tag);

        $this->assertSame([$tag], $this->proceeded);
        $this->assertSame('proceeded', $result->getAttributes()['nonce']);
        $this->assertSame([], $this->collected);
    }

    public function testAStylesheetGoesToThePlatform(): void
    {
        $tag = new TagData('style', [], 'a{color:red}', false);

        $this->plugin()->aroundProcessTag($this->createStub(InlineUtil::class), $this->proceed(), $tag);

        $this->assertSame([$tag], $this->proceeded);
        $this->assertSame([], $this->collected);
    }

    public function testAScriptWithASourceKeepsItsHostPermissionWithoutANonce(): void
    {
        $tag = new TagData('script', ['src' => 'https://cdn.test/a.js'], self::SCRIPT, false);

        $result = $this->plugin()->aroundProcessTag($this->createStub(InlineUtil::class), $this->proceed(), $tag);

        $this->assertCount(1, $this->proceeded);
        $this->assertNull($this->proceeded[0]->getContent());
        $this->assertSame(['src' => 'https://cdn.test/a.js'], $this->proceeded[0]->getAttributes());
        $this->assertSame($tag, $result);
        $this->assertSame([], $this->collected);
    }

    public function testAScriptWithASourceThePlatformFindsNothingToAllowIsLeftAlone(): void
    {
        $tag = new TagData('script', ['src' => '/static/a.js'], self::SCRIPT, false);
        $refusing = function (TagData $data): TagData {
            $this->proceeded[] = $data;
            throw new InvalidArgumentException('Either remote URL or hashable content is required to whitelist');
        };

        $result = $this->plugin()->aroundProcessTag($this->createStub(InlineUtil::class), $refusing, $tag);

        $this->assertSame($tag, $result);
        $this->assertSame([], $this->collected);
    }

    public function testInlineScriptsTheConfigurationAllowsGoToThePlatform(): void
    {
        $tag = new TagData('script', [], self::SCRIPT, false);

        $this->plugin(inlineAllowed: true)
            ->aroundProcessTag($this->createStub(InlineUtil::class), $this->proceed(), $tag);

        $this->assertSame([$tag], $this->proceeded);
        $this->assertSame([], $this->collected);
    }

    public function testOutsideAnObsidianThemeEveryTagGoesToThePlatform(): void
    {
        $tag = new TagData('script', [], self::SCRIPT, false);

        $this->plugin(obsidian: false)->aroundProcessTag($this->createStub(InlineUtil::class), $this->proceed(), $tag);

        $this->assertSame([$tag], $this->proceeded);
        $this->assertSame([], $this->collected);
    }

    public function testAScriptWithoutContentGoesToThePlatform(): void
    {
        $tag = new TagData('script', ['src' => 'https://cdn.test/a.js'], '', false);

        $this->plugin()->aroundProcessTag($this->createStub(InlineUtil::class), $this->proceed(), $tag);

        $this->assertSame([$tag], $this->proceeded);
    }

    private function proceed(): callable
    {
        return function (TagData $data): TagData {
            $this->proceeded[] = $data;

            return new TagData(
                $data->getTag(),
                $data->getAttributes() + ['nonce' => 'proceeded'],
                $data->getContent(),
                $data->isTextContent()
            );
        };
    }

    private function plugin(
        bool $obsidian = true,
        bool $inlineAllowed = false,
        bool $storable = true
    ): AuthorizeCacheableScriptsByHash {
        $themeDetector = $this->createStub(ObsidianThemeDetector::class);
        $themeDetector->method('isActive')->willReturn($obsidian);
        $inlineScriptPolicy = $this->createStub(InlineScriptPolicy::class);
        $inlineScriptPolicy->method('allowsInlineScripts')->willReturn($inlineAllowed);
        $cacheability = $this->createStub(ResponseCacheability::class);
        $cacheability->method('mayBeStored')->willReturn($storable);
        $collector = $this->createStub(DynamicCollector::class);
        $collector->method('add')->willReturnCallback(function (PolicyInterface $policy): void {
            $this->collected[] = $policy;
        });

        return new AuthorizeCacheableScriptsByHash($themeDetector, $inlineScriptPolicy, $cacheability, $collector);
    }
}
