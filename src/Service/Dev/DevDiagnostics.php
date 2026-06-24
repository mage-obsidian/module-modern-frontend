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
