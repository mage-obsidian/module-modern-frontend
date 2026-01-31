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
 * Immutable result of a single dev-environment diagnostic check.
 */
final class CheckResult
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARN = 'warn';
    public const STATUS_ERROR = 'error';

    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly string $message,
        public readonly string $hint = ''
    ) {
    }

    public static function ok(string $name, string $message): self
    {
        return new self($name, self::STATUS_OK, $message);
    }

    public static function warn(string $name, string $message, string $hint = ''): self
    {
        return new self($name, self::STATUS_WARN, $message, $hint);
    }

    public static function error(string $name, string $message, string $hint = ''): self
    {
        return new self($name, self::STATUS_ERROR, $message, $hint);
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }
}
