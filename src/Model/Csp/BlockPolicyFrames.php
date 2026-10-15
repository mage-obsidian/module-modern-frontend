<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Model\Csp;

use Magento\Csp\Api\Data\PolicyInterface;
use Magento\Csp\Model\Policy\FetchPolicy;

class BlockPolicyFrames
{
    /**
     * @var array<string, array<string, array{hosts: array<string, true>, hashes: array<string, string>}>>
     */
    private array $frames = [];

    public function open(string $identifier): void
    {
        $this->frames[$identifier] = [];
    }

    public function discard(string $identifier): void
    {
        unset($this->frames[$identifier]);
    }

    public function isOpen(string $identifier): bool
    {
        return array_key_exists($identifier, $this->frames);
    }

    public function record(PolicyInterface $policy): void
    {
        if (!$policy instanceof FetchPolicy || $this->frames === []) {
            return;
        }

        $hosts = array_fill_keys($policy->getHostSources(), true);
        $hashes = $policy->getHashes();
        if ($hosts === [] && $hashes === []) {
            return;
        }

        foreach (array_keys($this->frames) as $identifier) {
            $recorded = $this->frames[$identifier][$policy->getId()] ?? ['hosts' => [], 'hashes' => []];
            $this->frames[$identifier][$policy->getId()] = [
                'hosts' => $recorded['hosts'] + $hosts,
                'hashes' => $recorded['hashes'] + $hashes,
            ];
        }
    }

    /**
     * @return list<array{id: string, hosts: list<string>, hashes: array<string, string>}>
     */
    public function close(string $identifier): array
    {
        $recorded = $this->frames[$identifier] ?? [];
        unset($this->frames[$identifier]);

        $policies = [];
        foreach ($recorded as $id => $sources) {
            $policies[] = [
                'id' => (string)$id,
                'hosts' => array_keys($sources['hosts']),
                'hashes' => $sources['hashes'],
            ];
        }

        return $policies;
    }
}
