<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Contract;

final class ContractPaths
{
    private const array SECTIONS = ['modules', 'themes'];

    public static function relativize(array $contract, string $root): array
    {
        return self::mapSources($contract, static fn (string $src): string => self::relative($src, $root));
    }

    public static function absolutize(array $contract, string $root): array
    {
        return self::mapSources($contract, static fn (string $src): string => self::absolute($src, $root));
    }

    public static function relative(string $path, string $root): string
    {
        $prefix = rtrim($root, '/') . '/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    public static function absolute(string $path, string $root): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($root, '/') . '/' . $path;
    }

    private static function mapSources(array $contract, callable $map): array
    {
        foreach (self::SECTIONS as $section) {
            if (!is_array($contract[$section] ?? null)) {
                continue;
            }
            foreach ($contract[$section] as $name => $entry) {
                if (is_array($entry) && is_string($entry['src'] ?? null)) {
                    $contract[$section][$name]['src'] = $map($entry['src']);
                }
            }
        }

        return $contract;
    }
}
