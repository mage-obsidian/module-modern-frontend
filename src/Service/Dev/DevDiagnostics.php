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
    public const DEV_SERVER_HINT = 'Start the dev server: mage-obsidian:build-themes --theme <theme> --dev-server';

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
