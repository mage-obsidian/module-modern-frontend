<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Dev;

/**
 * Performs a lightweight HTTP probe and never throws — failures are reported as
 * a {@see ProbeResult} so diagnostics can interpret them uniformly.
 */
interface HttpProberInterface
{
    public function probe(string $url): ProbeResult;
}
