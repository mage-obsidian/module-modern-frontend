<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Dev;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;

/**
 * The deterministic Magento-side steps behind the one-shot dev up/down flow:
 * toggling the HMR flag (and flushing the caches that gate it) and ensuring a
 * dev server is reachable. Deliberately the *only* place these side effects are
 * sequenced, so the command stays a thin wrapper and this stays unit testable
 * against mocked collaborators.
 *
 * Starting the server is probe-first: if one already answers (e.g. a dev-server
 * container the environment manages), it is left alone instead of spawning a
 * second, unreachable one next to bin/magento.
 */
final class DevWorkflow
{
    private const CACHE_TYPE_CONFIG = 'config';
    private const CACHE_TYPE_FULL_PAGE = 'full_page';

    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly TypeListInterface $cacheTypeList,
        private readonly DevServerProcess $devServerProcess,
        private readonly HttpProberInterface $prober,
        private readonly ConfigProvider $configProvider
    ) {
    }

    /**
     * Persist the HMR flag and flush the caches that gate it so the change is
     * effective without a manual `cache:flush`.
     */
    public function setHmr(bool $enabled): void
    {
        $this->configWriter->save(ConfigProvider::HMR_ENABLED, $enabled ? '1' : '0');
        $this->cacheTypeList->cleanType(self::CACHE_TYPE_CONFIG);
        $this->cacheTypeList->cleanType(self::CACHE_TYPE_FULL_PAGE);
    }

    /**
     * Whether a Vite dev server already answers at the configured host:port.
     */
    public function isDevServerReachable(): bool
    {
        $url = $this->devServerProbeUrl();
        if ($url === null) {
            return false;
        }

        return $this->prober->probe($url)->isJavaScript();
    }

    /**
     * Make sure a dev server is running for the theme. Returns what happened so
     * the caller can report it:
     *  - 'already-running': one was already reachable; nothing started.
     *  - 'skipped': not reachable but starting was opted out (--no-start).
     *  - 'started': spawned here, with ['pid','theme','log'] under 'info'.
     *
     * @return array{action:string, info?:array{pid:int, theme:string, log:string}}
     * @throws LocalizedException
     */
    public function ensureDevServerRunning(string $theme, bool $noStart): array
    {
        if ($this->isDevServerReachable()) {
            return ['action' => 'already-running'];
        }

        if ($noStart) {
            return ['action' => 'skipped'];
        }

        return ['action' => 'started', 'info' => $this->devServerProcess->start($theme)];
    }

    /**
     * Stop a locally spawned dev server (no-op if none is tracked). Returns the
     * stopped pid or null.
     *
     * @throws LocalizedException
     */
    public function stopDevServer(): ?int
    {
        return $this->devServerProcess->stop();
    }

    private function devServerProbeUrl(): ?string
    {
        $vars = $this->configProvider->getViteEnvVars();
        $host = $vars[ViteEnvFile::VAR_SERVER_HOST] ?? '';
        $port = $vars[ViteEnvFile::VAR_SERVER_PORT] ?? '';
        if ($host === '' || $port === '') {
            return null;
        }

        return sprintf('http://%s:%s/@vite/client', $host, $port);
    }
}
