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
 * Immutable outcome of an HTTP probe against the dev server / proxy.
 */
final class ProbeResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly int $statusCode = 0,
        public readonly string $contentType = '',
        public readonly ?string $error = null
    ) {
    }

    public function isJavaScript(): bool
    {
        $type = strtolower($this->contentType);
        return str_contains($type, 'javascript') || str_contains($type, 'ecmascript');
    }

    /**
     * Human-readable reason a probe failed, for diagnostics messages.
     */
    public function describeFailure(): string
    {
        if ($this->error !== null && $this->error !== '') {
            return $this->error;
        }
        return $this->statusCode > 0 ? 'HTTP ' . $this->statusCode : 'no response';
    }
}
