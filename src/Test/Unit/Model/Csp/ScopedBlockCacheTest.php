<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Csp;

use Magento\Csp\Api\Data\PolicyInterface;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Collector\FetchPolicyMerger;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Serialize;
use MageObsidian\ModernFrontend\Model\Csp\BlockPolicyFrames;
use MageObsidian\ModernFrontend\Model\Csp\ScopedBlockCache;
use MageObsidian\ModernFrontend\Plugin\Csp\RecordBlockPolicies;
use PHPUnit\Framework\TestCase;

class ScopedBlockCacheTest extends TestCase
{
    private array $storage = [];

    public function testABlockStoresOnlyTheHashesAddedWhileItRendered(): void
    {
        [$cache, $collector] = $this->request();
        $collector->add($this->styleHash('page-wide'));

        $this->render($cache, $collector, 'block', 'own');

        $this->assertSame(['own'], $this->storedHashes('block'));
    }

    public function testAParentKeepsTheHashesOfAChildRenderedInsideIt(): void
    {
        [$cache, $collector] = $this->request();

        $cache->load('parent');
        $collector->add($this->styleHash('parent-own'));
        $this->render($cache, $collector, 'child', 'child-own');
        $cache->save('<parent/>', 'parent');

        $this->assertEqualsCanonicalizing(['parent-own', 'child-own'], $this->storedHashes('parent'));
        $this->assertSame(['child-own'], $this->storedHashes('child'));
    }

    public function testAParentKeepsTheHashesOfAChildLoadedFromCache(): void
    {
        [$cache, $collector] = $this->request();
        $this->render($cache, $collector, 'child', 'child-own');

        [$cache, $collector] = $this->request();
        $cache->load('parent');
        $cache->load('child');
        $cache->save('<parent/>', 'parent');

        $this->assertSame(['child-own'], $this->storedHashes('parent'));
    }

    public function testCachedHashesDoNotSnowballAcrossRequests(): void
    {
        for ($variant = 1; $variant <= 20; $variant++) {
            [$cache, $collector] = $this->request();
            $this->render($cache, $collector, 'listing-' . $variant, 'listing-' . $variant);
            $this->render($cache, $collector, 'navigation', 'navigation');
        }

        [$cache, $collector] = $this->request();
        $this->render($cache, $collector, 'listing-21', 'listing-21');
        $this->render($cache, $collector, 'navigation', 'navigation');

        $this->assertSame(['navigation'], $this->storedHashes('navigation'));
        $this->assertEqualsCanonicalizing(['listing-21', 'navigation'], $this->collectedHashes($collector));
    }

    public function testABlockLoadedFromCacheRestoresItsHashes(): void
    {
        [$cache, $collector] = $this->request();
        $this->render($cache, $collector, 'block', 'own');

        [$cache, $collector] = $this->request();

        $this->assertSame('<block/>', $cache->load('block'));
        $this->assertSame(['own'], $this->collectedHashes($collector));
    }

    public function testABlockThatAddedNothingIsStoredAsPlainHtml(): void
    {
        [$cache, $collector] = $this->request();
        $collector->add($this->styleHash('page-wide'));

        $cache->load('block');
        $cache->save('<block/>', 'block');

        $this->assertSame('<block/>', $this->storage['block']);
    }

    public function testDataSavedWithoutALoadKeepsThePlatformBehaviour(): void
    {
        [$cache, $collector] = $this->request();
        $collector->add($this->styleHash('page-wide'));

        $cache->save('payload', 'direct');

        $this->assertSame(['page-wide'], $this->storedHashes('direct'));
    }

    private function request(): array
    {
        $frames = new BlockPolicyFrames();
        $plugin = new RecordBlockPolicies($frames);
        $recording = new class (new DynamicCollector(new FetchPolicyMerger()), $plugin) extends DynamicCollector {
            public function __construct(
                private readonly DynamicCollector $inner,
                private readonly RecordBlockPolicies $plugin
            ) {
            }

            public function add(PolicyInterface $policy): void
            {
                $this->inner->add($policy);
                $this->plugin->afterAdd($this->inner, null, $policy);
            }

            public function collect(array $defaultPolicies = []): array
            {
                return $this->inner->collect($defaultPolicies);
            }
        };

        $backend = $this->createStub(CacheInterface::class);
        $backend->method('load')->willReturnCallback(fn(string $id) => $this->storage[$id] ?? false);
        $backend->method('save')->willReturnCallback(function ($data, string $id): bool {
            $this->storage[$id] = $data;

            return true;
        });

        return [new ScopedBlockCache($backend, $recording, new Serialize(), $frames), $recording];
    }

    private function render(ScopedBlockCache $cache, DynamicCollector $collector, string $id, string $hash): void
    {
        if ($cache->load($id) !== false) {
            return;
        }
        $collector->add($this->styleHash($hash));
        $cache->save('<' . explode('-', $id)[0] . '/>', $id);
    }

    private function styleHash(string $content): FetchPolicy
    {
        return new FetchPolicy('style-src', false, [], [], false, false, false, [], [$content => 'sha256']);
    }

    private function storedHashes(string $id): array
    {
        $data = (new Serialize())->unserialize($this->storage[$id]);
        $hashes = [];
        foreach ($data['policies'] as $policy) {
            $hashes = [...$hashes, ...array_keys($policy['hashes'])];
        }

        return $hashes;
    }

    private function collectedHashes(DynamicCollector $collector): array
    {
        $hashes = [];
        foreach ($collector->collect() as $policy) {
            $hashes = [...$hashes, ...array_keys($policy->getHashes())];
        }

        return $hashes;
    }
}
