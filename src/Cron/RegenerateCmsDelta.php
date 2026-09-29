<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Cron;

use MageObsidian\ModernFrontend\Service\Cms\DeltaStylesheet;

/**
 * The net under the save observers.
 *
 * Content also arrives through imports, the REST API and data patches, none of
 * which dispatch `cms_*_save_after`. Checking here rather than on render keeps
 * the work off the request path and means no thundering herd when a cached page
 * expires — the cost is that such content waits for the next run.
 */
class RegenerateCmsDelta
{
    public function __construct(private readonly DeltaStylesheet $delta)
    {
    }

    public function execute(): void
    {
        $this->delta->regenerate();
    }
}
