<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Csp;

use Magento\Csp\Api\Data\ModeConfiguredInterface;
use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Model\Mode\Data\ModeConfiguredFactory;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Service\Csp\RecommendedPolicy;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;

class EnforceRecommendedPolicy
{
    public function __construct(
        private readonly RecommendedPolicy $recommendedPolicy,
        private readonly ObsidianThemeDetector $themeDetector,
        private readonly ConfigProvider $configProvider,
        private readonly ModeConfiguredFactory $modeConfiguredFactory
    ) {
    }

    public function afterGetConfigured(
        ModeConfigManagerInterface $subject,
        ModeConfiguredInterface $result
    ): ModeConfiguredInterface {
        if (!$result->isReportOnly()
            || !$this->recommendedPolicy->isEnabled()
            || $this->configProvider->isHmrEnabled()
            || !$this->themeDetector->isActive()
        ) {
            return $result;
        }

        return $this->modeConfiguredFactory->create([
            'reportOnly' => false,
            'reportUri' => $result->getReportUri(),
        ]);
    }
}
