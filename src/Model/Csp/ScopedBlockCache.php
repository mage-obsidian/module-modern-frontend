<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Model\Csp;

use Magento\Csp\Model\BlockCache;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

class ScopedBlockCache extends BlockCache
{
    public function __construct(
        private readonly CacheInterface $cache,
        DynamicCollector $dynamicCollector,
        private readonly SerializerInterface $serializer,
        private readonly BlockPolicyFrames $frames
    ) {
        parent::__construct($cache, $dynamicCollector, $serializer);
    }

    public function load($identifier)
    {
        $loaded = parent::load($identifier);
        if ($loaded === false) {
            $this->frames->open((string)$identifier);
        } else {
            $this->frames->discard((string)$identifier);
        }

        return $loaded;
    }

    public function save($data, $identifier, $tags = [], $lifeTime = null)
    {
        if (!$this->frames->isOpen((string)$identifier)) {
            return parent::save($data, $identifier, $tags, $lifeTime);
        }

        $policies = $this->frames->close((string)$identifier);
        if ($policies !== []) {
            $data = $this->serializer->serialize(['policies' => $policies, 'html' => (string)$data]);
        }

        return $this->cache->save($data, $identifier, $tags, $lifeTime);
    }
}
