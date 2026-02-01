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
 * Pure mapping between Magento config (single source of truth) and the Vite
 * `.env` consumed by the JS build harness (vite.config.js / buildThemes.js).
 *
 * Keeping this free of Magento dependencies makes the derivation rules unit
 * testable in isolation: callers feed already-resolved scalars and receive an
 * ordered env-var map and the rendered file body.
 */
final class ViteEnvFile
{
    public const string VAR_SERVER_HOST = 'VITE_SERVER_HOST';
    public const string VAR_SERVER_PORT = 'VITE_SERVER_PORT';
    public const string VAR_SERVER_SECURE = 'VITE_SERVER_SECURE';
    public const string VAR_HMR_PATH = 'VITE_HMR_PATH';
    public const string VAR_PUBLIC_HOST = 'MAGENTO_HOST';
    public const string VAR_ALLOWED_HOSTS = 'VITE_SERVER_ALLOWED_HOSTS';

    /**
     * Build the ordered env-var map from resolved config scalars. `secure` is a
     * bool here and is serialized to the literal `true`/`false` tokens that
     * vite.config.js treats as truthy when choosing `wss` over `ws`.
     *
     * @return array<string, string>
     */
    public static function buildVars(
        string $host,
        string $port,
        bool $secure,
        string $hmrPath,
        string $publicHost,
        string $allowedHosts
    ): array {
        return [
            self::VAR_SERVER_HOST => $host,
            self::VAR_SERVER_PORT => $port,
            self::VAR_SERVER_SECURE => $secure ? 'true' : 'false',
            self::VAR_HMR_PATH => $hmrPath,
            self::VAR_PUBLIC_HOST => $publicHost,
            self::VAR_ALLOWED_HOSTS => $allowedHosts,
        ];
    }

    /**
     * Render the env-var map as a dotenv file body. Values containing
     * whitespace, `#` or quotes are double-quoted (with embedded quotes/
     * backslashes escaped) so dotenv parses them as a single token; plain
     * values (the common case: hosts, ports, comma lists) stay unquoted.
     *
     * @param array<string, string> $vars
     */
    public static function render(array $vars): string
    {
        $lines = [];
        foreach ($vars as $key => $value) {
            $lines[] = $key . '=' . self::encodeValue($value);
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }

    private static function encodeValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"\']/', $value) !== 1) {
            return $value;
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
