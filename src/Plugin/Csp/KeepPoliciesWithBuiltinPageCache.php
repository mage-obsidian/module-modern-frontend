<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Csp;

use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\Response\Http;
use MageObsidian\ModernFrontend\Model\Csp\DynamicPolicySnapshot;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;

class KeepPoliciesWithBuiltinPageCache
{
    public const string HEADER = 'X-Mage-Obsidian-Csp-Snapshot';

    public function __construct(
        private readonly ObsidianThemeDetector $themeDetector,
        private readonly DynamicPolicySnapshot $snapshot
    ) {
    }

    public function beforeProcess(Kernel $subject, Http $response): ?array
    {
        $snapshot = $this->snapshot->capture($this->themeDetector->isActive());
        if ($snapshot !== null) {
            $response->setHeader(self::HEADER, $snapshot, true);
        }

        return null;
    }

    public function afterProcess(Kernel $subject, mixed $result, Http $response): mixed
    {
        $response->clearHeader(self::HEADER);

        return $result;
    }

    public function afterLoad(Kernel $subject, mixed $result): mixed
    {
        if (!$result instanceof Http) {
            return $result;
        }

        $header = $result->getHeader(self::HEADER);
        if ($header === false || !$this->snapshot->restore((string)$header->getFieldValue())) {
            return false;
        }
        $result->clearHeader(self::HEADER);

        return $result;
    }
}
