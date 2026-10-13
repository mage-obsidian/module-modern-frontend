<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Csp;

use Magento\Csp\Model\Collector\ConfigCollector;
use Magento\Csp\Model\Policy\FetchPolicy;

class InlineStylePolicy
{
    private const string STYLE_POLICY = 'style-src';

    private ?bool $allowed = null;

    public function __construct(private readonly ConfigCollector $collector)
    {
    }

    public function allowsInlineStyles(): bool
    {
        if ($this->allowed !== null) {
            return $this->allowed;
        }

        foreach ($this->collector->collect() as $policy) {
            if ($policy instanceof FetchPolicy && $policy->getId() === self::STYLE_POLICY) {
                return $this->allowed = $policy->isInlineAllowed();
            }
        }

        return $this->allowed = true;
    }
}
