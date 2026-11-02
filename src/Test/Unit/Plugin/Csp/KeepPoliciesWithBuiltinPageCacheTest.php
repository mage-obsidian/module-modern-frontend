<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Csp;

use Laminas\Http\Header\GenericHeader;
use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\Response\Http;
use MageObsidian\ModernFrontend\Model\Csp\DynamicPolicySnapshot;
use MageObsidian\ModernFrontend\Plugin\Csp\KeepPoliciesWithBuiltinPageCache;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use PHPUnit\Framework\TestCase;

class KeepPoliciesWithBuiltinPageCacheTest extends TestCase
{
    private const string HEADER = KeepPoliciesWithBuiltinPageCache::HEADER;

    protected function setUp(): void
    {
        if (!class_exists(Kernel::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testAnObsidianResponseIsStoredWithItsPolicies(): void
    {
        $snapshot = $this->createMock(DynamicPolicySnapshot::class);
        $snapshot->expects($this->once())->method('capture')->with(true)->willReturn('c25hcHNob3Q=');
        $response = $this->createMock(Http::class);
        $response->expects($this->once())->method('setHeader')->with(self::HEADER, 'c25hcHNob3Q=', true);

        $this->assertNull($this->plugin($snapshot)->beforeProcess($this->createStub(Kernel::class), $response));
    }

    public function testAResponseUnderAnotherThemeIsStoredWithTheMarkOnly(): void
    {
        $snapshot = $this->createMock(DynamicPolicySnapshot::class);
        $snapshot->expects($this->once())->method('capture')->with(false)->willReturn('bWFyaw==');
        $response = $this->createMock(Http::class);
        $response->expects($this->once())->method('setHeader')->with(self::HEADER, 'bWFyaw==', true);

        $this->plugin($snapshot, obsidian: false)->beforeProcess($this->createStub(Kernel::class), $response);
    }

    public function testAResponseWhosePoliciesCannotBeKeptIsStoredWithoutTheMark(): void
    {
        $snapshot = $this->createStub(DynamicPolicySnapshot::class);
        $snapshot->method('capture')->willReturn(null);
        $response = $this->createMock(Http::class);
        $response->expects($this->never())->method('setHeader');

        $this->plugin($snapshot)->beforeProcess($this->createStub(Kernel::class), $response);
    }

    public function testTheMarkNeverReachesTheVisitor(): void
    {
        $response = $this->createMock(Http::class);
        $response->expects($this->once())->method('clearHeader')->with(self::HEADER);

        $this->assertSame(
            'result',
            $this->plugin($this->createStub(DynamicPolicySnapshot::class))
                ->afterProcess($this->createStub(Kernel::class), 'result', $response)
        );
    }

    public function testAHitPutsTheStoredPoliciesBackAndHidesTheMark(): void
    {
        $snapshot = $this->createMock(DynamicPolicySnapshot::class);
        $snapshot->expects($this->once())->method('restore')->with('c25hcHNob3Q=')->willReturn(true);
        $response = $this->responseCarrying('c25hcHNob3Q=');
        $response->expects($this->once())->method('clearHeader')->with(self::HEADER);

        $this->assertSame($response, $this->plugin($snapshot)->afterLoad($this->createStub(Kernel::class), $response));
    }

    public function testAnEntryWithoutTheMarkIsTreatedAsAMiss(): void
    {
        $response = $this->createStub(Http::class);
        $response->method('getHeader')->willReturn(false);

        $this->assertFalse(
            $this->plugin($this->createStub(DynamicPolicySnapshot::class))
                ->afterLoad($this->createStub(Kernel::class), $response)
        );
    }

    public function testAnEntryWhoseMarkCannotBeReadIsTreatedAsAMiss(): void
    {
        $snapshot = $this->createStub(DynamicPolicySnapshot::class);
        $snapshot->method('restore')->willReturn(false);

        $this->assertFalse(
            $this->plugin($snapshot)->afterLoad($this->createStub(Kernel::class), $this->responseCarrying('broken'))
        );
    }

    public function testAMissPassesThrough(): void
    {
        $this->assertFalse(
            $this->plugin($this->createStub(DynamicPolicySnapshot::class))
                ->afterLoad($this->createStub(Kernel::class), false)
        );
    }

    public function testAHitIsDecidedWithoutAskingForTheTheme(): void
    {
        $themeDetector = $this->createMock(ObsidianThemeDetector::class);
        $themeDetector->expects($this->never())->method('isActive');
        $snapshot = $this->createStub(DynamicPolicySnapshot::class);
        $snapshot->method('restore')->willReturn(true);

        (new KeepPoliciesWithBuiltinPageCache($themeDetector, $snapshot))
            ->afterLoad($this->createStub(Kernel::class), $this->responseCarrying('c25hcHNob3Q='));
    }

    private function responseCarrying(string $value): Http
    {
        $response = $this->createMock(Http::class);
        $response->method('getHeader')->with(self::HEADER)->willReturn(new GenericHeader(self::HEADER, $value));

        return $response;
    }

    private function plugin(DynamicPolicySnapshot $snapshot, bool $obsidian = true): KeepPoliciesWithBuiltinPageCache
    {
        $themeDetector = $this->createStub(ObsidianThemeDetector::class);
        $themeDetector->method('isActive')->willReturn($obsidian);

        return new KeepPoliciesWithBuiltinPageCache($themeDetector, $snapshot);
    }
}
