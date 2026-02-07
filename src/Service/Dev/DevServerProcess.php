<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Dev;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DriverInterface;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Manages the local lifecycle of the Vite dev server (a long-lived Node process)
 * from the ephemeral `bin/magento` CLI.
 *
 * Deliberately environment-agnostic: it always starts the process *here*, where
 * the command runs, and never reasons about containers, hosts or how the project
 * is mounted. Bridging that to a specific topology (e.g. running the dev server
 * in a different container than bin/magento) is the environment's job, not the
 * framework's.
 *
 * The process is detached into its own session/process group via `setsid` so it
 * outlives the CLI invocation, and stop signals the whole group so the
 * pnpm → node → vite child tree is torn down together. A small JSON pid file
 * tracks the leader pid and the theme it serves.
 */
class DevServerProcess
{
    private const PACKAGE_MANAGER = 'pnpm';
    private const VITE_DIR = 'vite';
    private const PID_DIR = 'mage_obsidian';
    private const PID_FILE = 'dev_server.json';
    private const LOG_FILE = 'mage_obsidian_dev_server.log';

    /** errno for "operation not permitted": the pid exists but is another user's. */
    private const EPERM = 1;

    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly DriverInterface $fileDriver,
        private readonly ExecutableFinder $executableFinder
    ) {
    }

    /**
     * Start the dev server for a theme. Returns the leader pid, theme and log path.
     *
     * @return array{pid:int, theme:string, log:string}
     * @throws LocalizedException
     */
    public function start(string $theme): array
    {
        $current = $this->status();
        if ($current['running']) {
            throw new LocalizedException(__(
                'The dev server is already running (pid %1, theme "%2"). Stop it first.',
                $current['pid'],
                $current['theme'] ?? 'unknown'
            ));
        }

        $binary = $this->resolvePackageManager();
        $root = $this->directoryList->getRoot();
        $this->assertViteHarnessExists($root);

        $logFile = $this->logFilePath();
        $spawnCommand = self::buildSpawnCommand($binary, $theme, $logFile);

        $pid = $this->spawnDetached($spawnCommand, $root);
        if ($pid <= 0) {
            throw new LocalizedException(__('Could not start the dev server (no pid returned). Check %1.', $logFile));
        }

        $this->writeState($pid, $theme);

        return ['pid' => $pid, 'theme' => $theme, 'log' => $logFile];
    }

    /**
     * Stop the running dev server. Returns the pid that was stopped, or null if
     * nothing was running (a stale pid file is cleaned up either way).
     *
     * @throws LocalizedException
     */
    public function stop(): ?int
    {
        $state = $this->readState();
        $pid = $state['pid'] ?? null;

        if ($pid === null || !$this->isAlive($pid)) {
            $this->clearState();
            return null;
        }

        $this->signalGroup($pid, 15); // SIGTERM
        $this->clearState();

        return $pid;
    }

    /**
     * @return array{running:bool, pid:int|null, theme:string|null}
     */
    public function status(): array
    {
        $state = $this->readState();
        $pid = $state['pid'] ?? null;
        $running = $pid !== null && $this->isAlive($pid);

        return [
            'running' => $running,
            'pid' => $running ? $pid : null,
            'theme' => $running ? ($state['theme'] ?? null) : null,
        ];
    }

    /**
     * Build the package-manager argument vector for the dev server. Mirrors the
     * deploy build invocation (`pnpm --prefix vite ...`) but targets the `dev`
     * script (buildThemes.js --dev-server) for a single theme.
     *
     * @return string[]
     */
    public static function buildDevServerArgs(string $packageManager, string $theme): array
    {
        return [$packageManager, '--prefix', self::VITE_DIR, 'dev', '--theme=' . $theme];
    }

    /**
     * Build the shell command that detaches the dev server into its own session
     * (so it survives the CLI) and echoes the leader pid. Every argument is shell
     * escaped, so a hostile theme name cannot inject commands.
     */
    public static function buildSpawnCommand(string $packageManager, string $theme, string $logFile): string
    {
        $argv = self::buildDevServerArgs($packageManager, $theme);
        $escaped = implode(' ', array_map('escapeshellarg', $argv));

        return sprintf('setsid %s > %s 2>&1 & echo $!', $escaped, escapeshellarg($logFile));
    }

    public static function encodeState(int $pid, string $theme): string
    {
        return (string)json_encode(['pid' => $pid, 'theme' => $theme]);
    }

    /**
     * @return array{pid?:int, theme?:string}
     */
    public static function decodeState(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['pid'])) {
            return [];
        }

        return [
            'pid' => (int)$decoded['pid'],
            'theme' => isset($decoded['theme']) ? (string)$decoded['theme'] : '',
        ];
    }

    /**
     * Run the spawn command from the Magento root and return the detached pid.
     * The shell exits immediately; the `setsid` child is reparented to init and
     * keeps running.
     */
    private function spawnDetached(string $spawnCommand, string $cwd): int
    {
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(['/bin/sh', '-c', $spawnCommand], $descriptors, $pipes, $cwd);
        if (!is_resource($process)) {
            return 0;
        }

        $pidRaw = stream_get_contents($pipes[1]) ?: '';
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($process);

        return (int)trim($pidRaw);
    }

    private function isAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (function_exists('posix_kill')) {
            // Signal 0 probes existence without affecting the process; EPERM
            // means it is alive but owned by another user (still "running").
            return posix_kill($pid, 0) || posix_get_last_error() === self::EPERM;
        }

        return $this->fileDriver->isExists('/proc/' . $pid);
    }

    private function signalGroup(int $pid, int $signal): void
    {
        // Negative pid targets the whole process group (the detached session),
        // tearing down pnpm and its node/vite children together.
        if (function_exists('posix_kill')) {
            posix_kill(-$pid, $signal);
            return;
        }
        @exec(sprintf('kill -%d -- -%d 2>/dev/null', $signal, $pid));
    }

    private function resolvePackageManager(): string
    {
        $binary = $this->executableFinder->find(self::PACKAGE_MANAGER);
        if ($binary === null) {
            throw new LocalizedException(__(
                'Cannot start the dev server: "%1" was not found in PATH. '
                . 'Run this where the Node toolchain is available.',
                self::PACKAGE_MANAGER
            ));
        }

        return $binary;
    }

    private function assertViteHarnessExists(string $root): void
    {
        $viteDir = $root . DIRECTORY_SEPARATOR . self::VITE_DIR;
        if (!$this->fileDriver->isDirectory($viteDir)) {
            throw new LocalizedException(__(
                'Cannot start the dev server: the build harness directory "%1" was not found.',
                $viteDir
            ));
        }
    }

    private function pidFilePath(): string
    {
        return $this->directoryList->getPath(DirectoryList::VAR_DIR)
            . DIRECTORY_SEPARATOR . self::PID_DIR
            . DIRECTORY_SEPARATOR . self::PID_FILE;
    }

    private function logFilePath(): string
    {
        return $this->directoryList->getPath(DirectoryList::LOG)
            . DIRECTORY_SEPARATOR . self::LOG_FILE;
    }

    /**
     * @return array{pid?:int, theme?:string}
     */
    private function readState(): array
    {
        $path = $this->pidFilePath();
        if (!$this->fileDriver->isExists($path)) {
            return [];
        }

        return self::decodeState($this->fileDriver->fileGetContents($path));
    }

    private function writeState(int $pid, string $theme): void
    {
        $path = $this->pidFilePath();
        $dir = $this->directoryList->getPath(DirectoryList::VAR_DIR) . DIRECTORY_SEPARATOR . self::PID_DIR;
        if (!$this->fileDriver->isDirectory($dir)) {
            $this->fileDriver->createDirectory($dir);
        }
        $this->fileDriver->filePutContents($path, self::encodeState($pid, $theme));
    }

    private function clearState(): void
    {
        $path = $this->pidFilePath();
        if ($this->fileDriver->isExists($path)) {
            $this->fileDriver->deleteFile($path);
        }
    }
}
