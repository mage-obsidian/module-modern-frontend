<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Model\Deploy;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ViteBuildRunner
{
    public const string VITE_DIR = 'vite';
    public const float DEFAULT_BUILD_TIMEOUT = 1800.0;
    public const string BUILD_TIMEOUT_ENV_VAR = 'MAGE_OBSIDIAN_VITE_BUILD_TIMEOUT';
    private const string PACKAGE_MANAGER = 'pnpm';

    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly ExecutableFinder $executableFinder,
        private readonly OutputInterface $output
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function build(?string $theme = null): void
    {
        $binary = $this->resolvePackageManager();
        $workingDirectory = $this->directoryList->getRoot();
        $this->assertViteHarnessExists($workingDirectory);

        $process = new Process(self::buildCommandArgs($binary, $theme), $workingDirectory);
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

    public static function resolveTimeout(string|false $raw): ?float
    {
        if ($raw === false || $raw === '' || !is_numeric($raw)) {
            return self::DEFAULT_BUILD_TIMEOUT;
        }
        $timeout = (float)$raw;
        return $timeout > 0 ? $timeout : null;
    }

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
