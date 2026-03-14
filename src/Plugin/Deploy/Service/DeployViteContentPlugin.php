<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Service;

use Magento\Deploy\Console\DeployStaticOptions;
use Magento\Deploy\Service\DeployStaticContent;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Drives the Vite build before Magento materializes static content.
 *
 * The Vite bundle is locale-agnostic, so it is built once per modern theme and
 * written to the theme source (`web/generated`). Magento's native deploy
 * pipeline then publishes that output to `pub/static/<area>/<theme>/<locale>/`
 * for every locale on its own — this plugin only produces the bundle, it does
 * not copy anything to pub/static.
 */
class DeployViteContentPlugin
{
    public const AVAILABLE_AREAS = ['frontend', 'all'];

    /**
     * Package manager that drives the Vite build harness.
     */
    private const PACKAGE_MANAGER = 'pnpm';

    /**
     * Vite harness directory, relative to the Magento root.
     */
    private const VITE_DIR = 'vite';

    /**
     * Default Vite build timeout in seconds. A finite default avoids the process
     * hanging forever on a stuck build. Override via the
     * MAGE_OBSIDIAN_VITE_BUILD_TIMEOUT env var (set to 0 to disable the limit).
     */
    public const DEFAULT_BUILD_TIMEOUT = 1800.0;
    public const BUILD_TIMEOUT_ENV_VAR = 'MAGE_OBSIDIAN_VITE_BUILD_TIMEOUT';

    public function __construct(
        private readonly ConfigManagerInterface $configManager,
        private readonly DirectoryList $directoryList,
        private readonly ExecutableFinder $executableFinder,
        private readonly OutputInterface $output
    ) {
    }

    /**
     * @param DeployStaticContent $subject
     * @param array $options
     * @return array
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDeploy(DeployStaticContent $subject, array $options): array
    {
        if (
            $options[DeployStaticOptions::NO_JAVASCRIPT] === true ||
            !$this->hasFrontendArea($options[DeployStaticOptions::AREA]) ||
            $this->hasFrontendArea($options[DeployStaticOptions::EXCLUDE_AREA])
        ) {
            return [$options];
        }

        $themes = $options[DeployStaticOptions::THEME] ?? [];
        $this->output->writeln('<info>Starting Mage Obsidian Vite build generation...</info>');

        if (in_array('all', $themes, true)) {
            $this->generateViteBuild();
        } else {
            foreach ($themes as $theme) {
                if (!$this->configManager->isThemeEnabled($theme)) {
                    continue;
                }
                $this->generateViteBuild($theme);
            }
        }

        $this->output->writeln('<info>Mage Obsidian Vite build generation finished.</info>');
        return [$options];
    }

    /**
     * @param string[] $areas
     * @return bool
     */
    private function hasFrontendArea(array $areas): bool
    {
        foreach ($areas as $area) {
            if (in_array($area, self::AVAILABLE_AREAS, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Run the Vite build for a single theme, or for every theme when null.
     *
     * @param string|null $theme
     * @return void
     * @throws LocalizedException
     */
    private function generateViteBuild(?string $theme = null): void
    {
        $binary = $this->resolvePackageManager();
        $workingDirectory = $this->directoryList->getRoot();
        $this->assertViteHarnessExists($workingDirectory);

        $process = new Process(
            self::buildCommandArgs($binary, $theme),
            $workingDirectory
        );
        $process->setTimeout(self::resolveTimeout(getenv(self::BUILD_TIMEOUT_ENV_VAR)));
        $process->run(function ($type, $buffer): void {
            if ($type === Process::ERR && $this->output instanceof ConsoleOutputInterface) {
                $this->output->getErrorOutput()->write($buffer);
                return;
            }
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new LocalizedException(__(
                'Vite build failed%1. %2',
                $theme !== null ? __(' for theme "%1"', $theme)->render() : '',
                $process->getErrorOutput() ?: $process->getOutput()
            ));
        }
    }

    /**
     * Build the package-manager argument vector.
     *
     * Passing the command as an argv array (not a shell string) keeps the theme
     * path from ever being interpreted by a shell, so a malformed or hostile
     * theme name cannot inject commands — unlike the previous interpolated
     * `Process::fromShellCommandline()` call.
     *
     * @param string $packageManager
     * @param string|null $theme
     * @return string[]
     */
    public static function buildCommandArgs(string $packageManager, ?string $theme): array
    {
        $args = [$packageManager, '--prefix', self::VITE_DIR, 'build'];
        if ($theme !== null) {
            $args[] = '--theme=' . str_replace('_', '/', $theme);
        }
        return $args;
    }

    /**
     * Resolve the build timeout in seconds from a raw env value.
     *
     * Returns null (no limit) only when explicitly set to 0/negative; an empty,
     * unset or non-numeric value falls back to a finite default so a stuck build
     * cannot hang the deploy forever.
     *
     * @param string|false $raw
     * @return float|null
     */
    public static function resolveTimeout(string|false $raw): ?float
    {
        if ($raw === false || $raw === '' || !is_numeric($raw)) {
            return self::DEFAULT_BUILD_TIMEOUT;
        }
        $timeout = (float)$raw;
        return $timeout > 0 ? $timeout : null;
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    private function resolvePackageManager(): string
    {
        $binary = $this->executableFinder->find(self::PACKAGE_MANAGER);
        if ($binary === null) {
            throw new LocalizedException(__(
                'Cannot build Vite assets: "%1" was not found in PATH. '
                . 'Install it (or expose it to the deploy environment) and retry.',
                self::PACKAGE_MANAGER
            ));
        }
        return $binary;
    }

    /**
     * @param string $root
     * @return void
     * @throws LocalizedException
     */
    private function assertViteHarnessExists(string $root): void
    {
        $viteDir = $root . DIRECTORY_SEPARATOR . self::VITE_DIR;
        if (!is_dir($viteDir)) {
            throw new LocalizedException(__(
                'Cannot build Vite assets: the build harness directory "%1" was not found. '
                . 'Ensure the mage-obsidian/component-modern-frontend mapping is in place.',
                $viteDir
            ));
        }
    }
}
