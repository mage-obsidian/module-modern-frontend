<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\Deploy;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DriverInterface;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;

/**
 * Checks that the Vite bundle a theme built is the one sitting in `pub/static`.
 *
 * This exists because `setup:static-content:deploy` cannot be trusted to say so,
 * and it gets there two different ways.
 *
 * `Magento\Deploy\Process\Queue::process()` returns a `$returnStatus` that is
 * initialised to 0 and never reassigned, and a worker killed mid-flight is still
 * marked `STATE_COMPLETED` — its exit status only ever reaches the log, as info.
 * So a deploy whose workers die leaves packages half-published and still exits 0,
 * which is how a storefront ends up served without a single line of JavaScript.
 *
 * The quieter one is `Magento\Framework\App\View\Asset\Publisher::publish()`,
 * which returns early the moment the destination exists. A Luma theme never
 * notices: its CSS is written by the pre-processor pipeline, which rewrites. Vite
 * output is copied verbatim and most of its filenames are stable — the theme
 * stylesheet, every enhancer, every island — so a rebuild followed by a redeploy
 * republishes only the files that did not exist before, and serves yesterday's
 * bundle for the rest. Nothing fails; the storefront just stops matching its
 * source. That is why presence is not enough and size and mtime are compared too.
 *
 * Only MageObsidian themes are inspected: they are the ones whose assets this
 * module is responsible for producing.
 */
class ViteOutputVerifier
{
    private const AREA = 'frontend';

    private const WEB_PATH = 'web';

    public function __construct(
        private readonly ConfigManagerInterface $configManager,
        private readonly DirectoryList $directoryList,
        private readonly DriverInterface $driver,
        private readonly DeployTargets $targets
    ) {
    }

    /**
     * The deploy options are taken whole rather than a locale list: they carry
     * sentinels (`all`, `none`) and exclusion rules that decide which packages
     * were written at all, and checking paths the run never meant to produce
     * reports a healthy deploy as a broken one.
     *
     * @param array<string, mixed> $options
     * @return array<string, ViteOutputTarget> keyed by "<theme>@<locale>"
     */
    public function findOutdated(array $options): array
    {
        $locales = $this->targets->locales($options);
        if ($locales === []) {
            return [];
        }

        $staticRoot = $this->directoryList->getPath(DirectoryList::STATIC_VIEW);
        $outdated = [];

        foreach ($this->configManager->get()['themes'] ?? [] as $theme => $definition) {
            if (!$this->targets->includesTheme((string)$theme, $options)) {
                continue;
            }

            $source = $definition['src'] . '/' . self::WEB_PATH . '/' . ConfigInterface::GENERATED_PATH;
            if (!$this->driver->isDirectory($source)) {
                continue;
            }

            $built = $this->relativePaths($source);
            foreach ($locales as $locale) {
                $target = $staticRoot . '/' . self::AREA . '/' . $theme . '/' . $locale
                    . '/' . ConfigInterface::GENERATED_PATH;

                $files = array_values(array_filter(
                    $built,
                    fn (string $file): bool => $this->isOutdated($source . '/' . $file, $target . '/' . $file)
                ));

                if ($files !== []) {
                    $entry = new ViteOutputTarget((string)$theme, (string)$locale, $source, $target, $files);
                    $outdated[$entry->label()] = $entry;
                }
            }
        }

        return $outdated;
    }

    /**
     * A published file is outdated when it is absent, when it is a different
     * size, or when the build wrote its source after it was published. Content
     * is deliberately not hashed: a theme publishes thousands of files per
     * locale and reading them all would cost more than the deploy itself.
     */
    private function isOutdated(string $source, string $target): bool
    {
        if (!$this->driver->isExists($target)) {
            return true;
        }

        try {
            $published = $this->driver->stat($target);
            $built = $this->driver->stat($source);
        } catch (FileSystemException) {
            // Unreadable is not proof of staleness, and republishing over a file
            // that cannot be stat'ed is unlikely to go better.
            return false;
        }

        return ($published['size'] ?? null) !== ($built['size'] ?? null)
            || (int)($published['mtime'] ?? 0) < (int)($built['mtime'] ?? 0);
    }

    /**
     * The listing includes the directories themselves; counting those as missing
     * assets inflates the total and fills the failure message with entries that
     * cannot be looked up.
     *
     * @return string[]
     */
    private function relativePaths(string $source): array
    {
        $prefix = rtrim($source, '/') . '/';
        $files = array_filter(
            $this->driver->readDirectoryRecursively($source),
            fn (string $path): bool => !$this->driver->isDirectory($path)
        );

        $relative = array_map(
            static fn (string $path): string => str_starts_with($path, $prefix)
                ? substr($path, strlen($prefix))
                : $path,
            $files
        );

        return array_values(array_filter($relative, static fn (string $path): bool => !self::isHidden($path)));
    }

    /**
     * Magento's deploy skips dot-files, and Vite writes its own manifest under
     * `.vite/`; expecting those in pub/static would fail every healthy deploy.
     */
    private static function isHidden(string $relative): bool
    {
        foreach (explode('/', $relative) as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }
}
