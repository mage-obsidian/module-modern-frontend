<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Observer\Cms;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageObsidian\ModernFrontend\Service\Cms\DeltaStylesheet;

/**
 * Rebuilds the delta stylesheet when content changes in the admin.
 *
 * This is the fast path, so the CSS is ready before anyone previews the page.
 * Content also changes through imports, the API and data patches, which never
 * reach an observer — the cron covers those.
 */
class RegenerateDelta implements ObserverInterface
{
    public function __construct(private readonly DeltaStylesheet $delta)
    {
    }

    public function execute(Observer $observer): void
    {
        $this->delta->regenerate();
    }
}
