<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Contract;

class ContractFileRefresher
{
    public function refresh(string $file): void
    {
        if (!function_exists('opcache_invalidate')) {
            return;
        }
        $script = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
        if (!self::apiAllowed((string)ini_get('opcache.restrict_api'), $script)) {
            return;
        }
        opcache_invalidate($file, false);
    }

    public static function apiAllowed(string $restrictApi, string $script): bool
    {
        return $restrictApi === '' || str_starts_with($script, $restrictApi);
    }
}
