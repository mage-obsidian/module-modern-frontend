<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Csp;

use Magento\Csp\Model\CompositePolicyCollector;
use MageObsidian\ModernFrontend\Service\Csp\InlineStylePolicy;
use MageObsidian\ModernFrontend\Service\Csp\RecommendedPolicy;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;

class ApplyRecommendedPolicy
{
    public function __construct(
        private readonly RecommendedPolicy $recommendedPolicy,
        private readonly ObsidianThemeDetector $themeDetector,
        private readonly InlineStylePolicy $inlineStylePolicy
    ) {
    }

    public function afterCollect(CompositePolicyCollector $subject, array $result): array
    {
        if (!$this->recommendedPolicy->isEnabled() || !$this->themeDetector->isActive()) {
            return $result;
        }

        return $this->recommendedPolicy->apply($result, $this->inlineStylePolicy->allowsInlineStyles());
    }
}
