<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Dev;

/**
 * Performs a lightweight HTTP probe and never throws — failures are reported as
 * a {@see ProbeResult} so diagnostics can interpret them uniformly.
 */
interface HttpProberInterface
{
    public function probe(string $url): ProbeResult;
}
