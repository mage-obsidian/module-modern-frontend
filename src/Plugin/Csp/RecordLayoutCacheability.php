<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Csp;

use Magento\Framework\View\Layout;
use MageObsidian\ModernFrontend\Service\Csp\ResponseCacheability;

class RecordLayoutCacheability
{
    public function __construct(private readonly ResponseCacheability $cacheability)
    {
    }

    public function afterGenerateElements(Layout $subject, mixed $result): mixed
    {
        $this->cacheability->recordLayout($subject->isCacheable());

        return $result;
    }
}
