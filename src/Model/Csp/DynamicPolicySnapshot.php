<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Model\Csp;

use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Psr\Log\LoggerInterface;

class DynamicPolicySnapshot
{
    private const int VERSION = 1;

    private const string UNKEPT_POLICY = 'MageObsidian cannot keep the "%s" policy with the page cache entry,'
        . ' so the page is rendered again on every hit.';

    public function __construct(
        private readonly DynamicCollector $collector,
        private readonly LoggerInterface $logger
    ) {
    }

    public function capture(bool $withPolicies): ?string
    {
        $policies = [];
        foreach ($withPolicies ? $this->collector->collect() : [] as $policy) {
            if (!$policy instanceof FetchPolicy) {
                $this->logger->warning(sprintf(self::UNKEPT_POLICY, $policy->getId()));

                return null;
            }
            $policies[] = $this->export($policy);
        }

        return base64_encode(json_encode(['version' => self::VERSION, 'policies' => $policies], JSON_THROW_ON_ERROR));
    }

    public function restore(string $snapshot): bool
    {
        $decoded = json_decode((string)base64_decode($snapshot, true), true);
        if (!is_array($decoded)
            || ($decoded['version'] ?? null) !== self::VERSION
            || !is_array($decoded['policies'] ?? null)
        ) {
            return false;
        }

        foreach ($decoded['policies'] as $policy) {
            $this->collector->add($this->import((array)$policy));
        }

        return true;
    }

    private function export(FetchPolicy $policy): array
    {
        return [
            'id' => $policy->getId(),
            'none' => $policy->isNoneAllowed(),
            'hosts' => $policy->getHostSources(),
            'schemes' => $policy->getSchemeSources(),
            'self' => $policy->isSelfAllowed(),
            'inline' => $policy->isInlineAllowed(),
            'eval' => $policy->isEvalAllowed(),
            'nonces' => $policy->getNonceValues(),
            'hashes' => $policy->getHashes(),
            'dynamic' => $policy->isDynamicAllowed(),
            'eventHandlers' => $policy->areEventHandlersAllowed(),
        ];
    }

    private function import(array $policy): FetchPolicy
    {
        return new FetchPolicy(
            (string)($policy['id'] ?? ''),
            (bool)($policy['none'] ?? false),
            (array)($policy['hosts'] ?? []),
            (array)($policy['schemes'] ?? []),
            (bool)($policy['self'] ?? false),
            (bool)($policy['inline'] ?? false),
            (bool)($policy['eval'] ?? false),
            (array)($policy['nonces'] ?? []),
            (array)($policy['hashes'] ?? []),
            (bool)($policy['dynamic'] ?? false),
            (bool)($policy['eventHandlers'] ?? false)
        );
    }
}
