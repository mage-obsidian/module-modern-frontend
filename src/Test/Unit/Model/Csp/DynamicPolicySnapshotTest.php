<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Csp;

use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Csp\Model\Policy\FlagPolicy;
use Magento\Csp\Model\Collector\FetchPolicyMerger;
use MageObsidian\ModernFrontend\Model\Csp\DynamicPolicySnapshot;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DynamicPolicySnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(DynamicCollector::class)) {
            $this->markTestSkipped('Magento_Csp is not available in this runtime.');
        }
    }

    public function testAHitGetsBackEveryAttributeOfEveryPolicyTheRenderCollected(): void
    {
        $rendered = $this->collector();
        $rendered->add(
            new FetchPolicy(
                'script-src',
                false,
                ['cdn.test'],
                ['https'],
                true,
                false,
                false,
                ['bm9uY2U='],
                ['aGFzaA==' => 'sha256']
            )
        );
        $rendered->add(
            new FetchPolicy('style-src', false, [], [], false, true, false, [], ['c3R5bGU=' => 'sha256'], true, true)
        );

        $hit = $this->collector();
        $this->assertTrue($this->snapshotOf($hit)->restore($this->snapshotOf($rendered)->capture(true)));

        $this->assertSame($this->describe($rendered), $this->describe($hit));
    }

    public function testEveryHitGetsBackTheSameSet(): void
    {
        $rendered = $this->collector();
        $rendered->add(new FetchPolicy('script-src', false, [], [], false, false, false, [], ['aGFzaA==' => 'sha256']));
        $snapshot = $this->snapshotOf($rendered)->capture(true);

        for ($visit = 0; $visit < 20; $visit++) {
            $hit = $this->collector();
            $this->snapshotOf($hit)->restore($snapshot);
            $this->assertSame($this->describe($rendered), $this->describe($hit));
        }
    }

    public function testASnapshotWithoutPoliciesStillMarksTheEntry(): void
    {
        $rendered = $this->collector();
        $rendered->add(new FetchPolicy('script-src', false, [], [], false, false, false, [], ['aGFzaA==' => 'sha256']));
        $hit = $this->collector();

        $this->assertTrue($this->snapshotOf($hit)->restore($this->snapshotOf($rendered)->capture(false)));
        $this->assertSame([], $hit->collect());
    }

    public function testAPolicyItCannotRebuildLeavesNoSnapshotAndSaysSo(): void
    {
        $rendered = $this->collector();
        $rendered->add(new FlagPolicy('upgrade-insecure-requests'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->assertNull((new DynamicPolicySnapshot($rendered, $logger))->capture(true));
    }

    public function testRefusesWhatItDidNotWrite(): void
    {
        $snapshot = $this->snapshotOf($this->collector());

        $this->assertFalse($snapshot->restore('not a snapshot'));
        $this->assertFalse($snapshot->restore(base64_encode('{"version":2,"policies":[]}')));
        $this->assertFalse($snapshot->restore(base64_encode('{"version":1}')));
    }

    private function collector(): DynamicCollector
    {
        return new DynamicCollector(new FetchPolicyMerger());
    }

    private function snapshotOf(DynamicCollector $collector): DynamicPolicySnapshot
    {
        return new DynamicPolicySnapshot($collector, $this->createStub(LoggerInterface::class));
    }

    private function describe(DynamicCollector $collector): array
    {
        return array_map(
            static fn(FetchPolicy $policy): array => [
                $policy->getId(),
                $policy->isNoneAllowed(),
                $policy->getHostSources(),
                $policy->getSchemeSources(),
                $policy->isSelfAllowed(),
                $policy->isInlineAllowed(),
                $policy->isEvalAllowed(),
                $policy->getNonceValues(),
                $policy->getHashes(),
                $policy->isDynamicAllowed(),
                $policy->areEventHandlersAllowed(),
            ],
            $collector->collect()
        );
    }
}
