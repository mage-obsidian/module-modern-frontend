<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Dev;

use MageObsidian\ModernFrontend\Service\Contract\ContractDiff;

/**
 * Pure interpretation of dev-environment signals into actionable check results.
 *
 * All inputs are primitives or {@see ProbeResult}, so every rule is unit-testable
 * without Magento. The CLI command gathers the inputs (app mode, HMR flag,
 * contract state, env vars, HTTP probes) and feeds them here.
 */
class DevDiagnostics
{
    public const DEV_SERVER_HINT = 'Start it: bin/magento mage-obsidian:frontend:dev --up';

    /**
     * Extensions a config file may carry. The engine loads exactly one filename
     * (MODULE_CONFIG_FILE / THEME_CONFIG_FILE); a sibling sharing the base name
     * but any of these other extensions is silently ignored at build time.
     */
    public const CONFIG_SHADOW_EXTENSIONS = ['js', 'cjs', 'mjs', 'ts'];

    /**
     * The page-cache identifier that builds the lookup key from the
     * X-Magento-Vary cookie. Kept as a literal so this class stays free of
     * Magento imports.
     */
    public const VARY_AWARE_IDENTIFIER = 'Magento\Framework\App\PageCache\Identifier';

    public const PAGE_CACHE_KERNEL = 'Magento\Framework\App\PageCache\Kernel';

    public const PAGE_CACHE_IDENTIFIER_INTERFACE = 'Magento\Framework\App\PageCache\IdentifierInterface';

    public const PAGE_CACHE_VARY_ISSUE_URL = 'https://github.com/magento/magento2/issues/40474';

    /**
     * The two spellings of the island helper, phtml and Twig.
     */
    public const ISLAND_HELPERS = ['renderVueComponent', 'render_vue'];

    /**
     * Find eager islands that still replace their container on mount.
     *
     * An eager island is above the fold, so replacing its container on mount
     * always moves the page. Only `$hydrate` makes Vue adopt the server markup.
     *
     * Pure so the rule is unit-testable; the caller supplies the template source.
     *
     * @param string $source Contents of one .twig or .phtml template.
     *
     * @return string[] Component names, in the order they appear.
     */
    public function eagerIslandsWithoutHydration(string $source): array
    {
        $found = [];
        foreach (self::ISLAND_HELPERS as $helper) {
            $offset = 0;
            while (($position = strpos($source, $helper . '(', $offset)) !== false) {
                $open = $position + strlen($helper);
                $arguments = $this->splitCallArguments($source, $open);
                $offset = $position + 1;

                if (($arguments[2] ?? '') !== 'true') {
                    continue;
                }
                if (($arguments[4] ?? '') === 'true') {
                    continue;
                }

                $found[] = trim($arguments[0] ?? '', " \t\n'\"");
            }
        }

        return $found;
    }

    /**
     * Split a call's top-level arguments, ignoring commas nested in strings,
     * parentheses, arrays or object literals.
     *
     * @param string $source
     * @param int $openParen Offset of the opening parenthesis.
     *
     * @return string[]
     */
    private function splitCallArguments(string $source, int $openParen): array
    {
        $arguments = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($source);

        for ($i = $openParen; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote && $source[$i - 1] !== '\\') {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if (str_contains('([{', $char)) {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif (str_contains(')]}', $char)) {
                $depth--;
                if ($depth === 0) {
                    $arguments[] = trim($current);
                    return $arguments;
                }
            } elseif ($char === ',' && $depth === 1) {
                $arguments[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        return $arguments;
    }

    /**
     * Report eager islands still mounting into an empty container.
     *
     * A warning, not an error: the page works, it just shifts.
     *
     * @param array<string, string[]> $islandsByTemplate Template path => component names.
     */
    public function evaluateIslandHydration(array $islandsByTemplate): CheckResult
    {
        $islandsByTemplate = array_filter($islandsByTemplate);
        if ($islandsByTemplate === []) {
            return CheckResult::ok('Island hydration', 'Every eager island hydrates its server-rendered state.');
        }

        $lines = [];
        $total = 0;
        foreach ($islandsByTemplate as $template => $components) {
            $total += count($components);
            $lines[] = sprintf('%s (%s)', $template, implode(', ', $components));
        }

        return CheckResult::warn(
            'Island hydration',
            sprintf(
                '%d eager island(s) replace their container on mount and shift the page: %s.',
                $total,
                implode('; ', $lines)
            ),
            'Render the component\'s initial state server-side and pass it as the fourth argument with '
                . '$hydrate = true, so Vue adopts it instead of replacing it. Generate the markup with '
                . 'mage-obsidian:island-ssr.'
        );
    }

    public function evaluateMode(string $mode): CheckResult
    {
        return CheckResult::ok('App mode', sprintf('Current mode: %s.', $mode));
    }

    public function evaluateContract(bool $exists, ?string $schemaVersion, string $expectedVersion): CheckResult
    {
        if (!$exists) {
            return CheckResult::error(
                'Contract',
                'Frontend contract file is missing.',
                'Generate it: bin/magento mage-obsidian:frontend:config --generate'
            );
        }
        if ($schemaVersion === null || $schemaVersion === '') {
            return CheckResult::error(
                'Contract',
                'Contract has no schema_version.',
                'Regenerate it: bin/magento mage-obsidian:frontend:config --generate'
            );
        }
        if ($schemaVersion !== $expectedVersion) {
            return CheckResult::warn(
                'Contract',
                sprintf('schema_version %s differs from expected %s.', $schemaVersion, $expectedVersion),
                'Regenerate the contract after updating the module.'
            );
        }

        return CheckResult::ok('Contract', sprintf('Valid (schema_version %s).', $schemaVersion));
    }

    /**
     * Interpret a contract drift (from ConfigManager::detectDrift). A non-empty
     * drift means the on-disk contract no longer matches the enabled
     * modules/themes — e.g. a compatibility flag was edited without re-toggling.
     *
     * @param array<string, array{added: string[], removed: string[], changed: string[]}> $drift
     */
    public function evaluateDrift(array $drift): CheckResult
    {
        if (ContractDiff::isEmpty($drift)) {
            return CheckResult::ok('Contract drift', 'Contract matches the enabled modules/themes.');
        }

        return CheckResult::warn(
            'Contract drift',
            sprintf('Contract is stale (%s).', ContractDiff::summarize($drift)),
            'Regenerate it: bin/magento mage-obsidian:frontend:config --generate'
        );
    }

    public function evaluateHmr(string $mode, bool $hmrEnabled): CheckResult
    {
        if ($mode === 'production') {
            return CheckResult::ok('HMR', 'Disabled in production (forced).');
        }
        if (!$hmrEnabled) {
            return CheckResult::warn(
                'HMR',
                'HMR is disabled; the storefront serves the built static output.',
                'Enable it: bin/magento mage-obsidian:frontend:hmr --enable'
            );
        }

        return CheckResult::ok('HMR', 'Enabled.');
    }

    public function evaluateDevServer(bool $hmrEnabled, ProbeResult $probe): CheckResult
    {
        if (!$hmrEnabled) {
            return CheckResult::ok('Dev server', 'Not required (HMR disabled).');
        }
        if (!$probe->ok) {
            return CheckResult::error(
                'Dev server',
                sprintf('Vite client unreachable (%s).', $probe->describeFailure()),
                self::DEV_SERVER_HINT
            );
        }
        if (!$probe->isJavaScript()) {
            return CheckResult::error(
                'Dev server',
                sprintf('/@vite/client responded but not as JavaScript (content-type: %s).', $probe->contentType ?: 'unknown'),
                'Check the nginx proxy that forwards /@vite to the dev server.'
            );
        }

        return CheckResult::ok('Dev server', 'Reachable (/@vite/client is served).');
    }

    /**
     * @param string[] $missingVars
     */
    public function evaluateEnv(array $missingVars): CheckResult
    {
        if ($missingVars !== []) {
            return CheckResult::warn(
                'Vite .env',
                'Missing variables: ' . implode(', ', $missingVars) . '.',
                'Add them to vite/.env (see vite/.env.sample).'
            );
        }

        return CheckResult::ok('Vite .env', 'All required variables present.');
    }

    /**
     * Given the config filename the engine actually loads (e.g. "module.config.ts")
     * and the filenames present in a directory, return those that share the config
     * base name but carry a different, build-ignored extension. Pure so the
     * shadowing rule is unit-testable; the caller supplies the directory listing.
     *
     * @param string[] $filenamesPresent
     * @return string[]
     */
    public function shadowsInDirectory(string $expectedFile, array $filenamesPresent): array
    {
        $base = pathinfo($expectedFile, PATHINFO_FILENAME);
        $expectedExt = pathinfo($expectedFile, PATHINFO_EXTENSION);

        $shadows = [];
        foreach ($filenamesPresent as $name) {
            if (pathinfo($name, PATHINFO_FILENAME) !== $base) {
                continue;
            }
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            if ($ext === $expectedExt) {
                continue;
            }
            if (in_array($ext, self::CONFIG_SHADOW_EXTENSIONS, true)) {
                $shadows[] = $name;
            }
        }

        return $shadows;
    }

    /**
     * Report config files the engine ignores because their extension differs
     * from the one the contract resolves. A warning (not an error): the build
     * still runs, but the author's config silently never loads.
     *
     * @param string[] $shadowed Paths of ignored config files.
     */
    public function evaluateShadowedConfigs(array $shadowed): CheckResult
    {
        if ($shadowed === []) {
            return CheckResult::ok('Config files', 'No ignored config files detected.');
        }

        return CheckResult::warn(
            'Config files',
            sprintf(
                '%d config file(s) ignored (extension differs from the one the engine loads): %s.',
                count($shadowed),
                implode(', ', $shadowed)
            ),
            'Rename each to module.config.ts / theme.config.js (or delete it) so the engine stops skipping it.'
        );
    }

    /**
     * Class the built-in page cache uses to build the *lookup* key. A
     * Kernel-level `identifier` argument wins over the interface preference,
     * so an install that already applied the workaround reads as healthy.
     *
     * @param array<string, mixed> $frontendDiConfig Merged DI config of the frontend area.
     */
    public function resolvePageCacheIdentifier(array $frontendDiConfig): string
    {
        // Compiled DI packs an object argument as `_i_`; the uncompiled reader
        // emits `instance`. Either form resolves to an Interceptor subclass when
        // the target carries plugins, which the caller does not care about.
        $argument = $frontendDiConfig['arguments'][self::PAGE_CACHE_KERNEL]['identifier'] ?? null;
        $instance = is_array($argument) ? ($argument['_i_'] ?? $argument['instance'] ?? null) : null;

        if (!is_string($instance) || $instance === '') {
            $instance = $frontendDiConfig['preferences'][self::PAGE_CACHE_IDENTIFIER_INTERFACE] ?? null;
        }
        if (!is_string($instance)) {
            return '';
        }

        return preg_replace('/\\\\Interceptor$/', '', ltrim($instance, '\\')) ?? '';
    }

    /**
     * Magento 2.4.7 decoupled the page-cache identifiers and left the lookup one
     * resolving to IdentifierForSave, which derives the key from an HTTP context
     * that is still empty when the cache is read. The X-Magento-Vary cookie is
     * therefore ignored and every visitor is served the first cached variant.
     * Varnish is unaffected: its VCL hashes the cookie itself.
     *
     * @param string[] $varyingDimensions Context dimensions that actually differ in this install.
     */
    public function evaluatePageCacheVary(
        bool $varnishEnabled,
        string $identifierClass,
        array $varyingDimensions
    ): CheckResult {
        if ($varnishEnabled) {
            return CheckResult::ok('Page cache vary', 'Varnish hashes X-Magento-Vary in its own VCL.');
        }
        if ($identifierClass === self::VARY_AWARE_IDENTIFIER) {
            return CheckResult::ok('Page cache vary', 'Built-in cache key honours X-Magento-Vary.');
        }

        $hint = sprintf(
            'Switch to Varnish, or give %s an "identifier" argument of %s. See %s.',
            self::PAGE_CACHE_KERNEL,
            self::VARY_AWARE_IDENTIFIER,
            self::PAGE_CACHE_VARY_ISSUE_URL
        );

        if ($varyingDimensions === []) {
            return CheckResult::warn(
                'Page cache vary',
                'Built-in cache key ignores the X-Magento-Vary cookie, but no context dimension varies yet.',
                $hint
            );
        }

        return CheckResult::error(
            'Page cache vary',
            sprintf(
                'Built-in cache key ignores the X-Magento-Vary cookie, so every visitor gets the first cached '
                . 'variant (varying: %s).',
                implode(', ', $varyingDimensions)
            ),
            $hint
        );
    }

    /**
     * @param CheckResult[] $results
     */
    public function hasError(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->isError()) {
                return true;
            }
        }

        return false;
    }
}
