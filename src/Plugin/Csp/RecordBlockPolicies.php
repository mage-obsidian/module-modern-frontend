<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Csp;

use Magento\Csp\Api\Data\PolicyInterface;
use Magento\Csp\Model\Collector\DynamicCollector;
use MageObsidian\ModernFrontend\Model\Csp\BlockPolicyFrames;

class RecordBlockPolicies
{
    public function __construct(private readonly BlockPolicyFrames $frames)
    {
    }

    public function afterAdd(DynamicCollector $subject, mixed $result, PolicyInterface $policy): mixed
    {
        $this->frames->record($policy);

        return $result;
    }
}
