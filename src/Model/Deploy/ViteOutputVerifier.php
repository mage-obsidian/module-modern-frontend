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
use Magento\Framework\Filesystem\DriverInterface;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;

/**
 * Checks that the Vite bundle a theme built actually reached `pub/static`.
 *
 * This exists because `setup:static-content:deploy` cannot be trusted to say so.
 * `Magento\Deploy\Process\Queue::process()` returns a `$returnStatus` that is
 * initialised to 0 and never reassigned, and a worker killed mid-flight is still
 * marked `STATE_COMPLETED` — its exit status only ever reaches the log, as info.
 * So a deploy whose workers die leaves packages half-published and still exits 0,
 * which is how a storefront ends up served without a single line of JavaScript.
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
        private readonly DriverInterface $driver
    ) {
    }

    /**
     * @param string[] $locales
     * @return array<string, string[]> "<theme>@<locale>" => paths relative to generated/
     */
    public function findMissing(array $locales): array
    {
        $staticRoot = $this->directoryList->getPath(DirectoryList::STATIC_VIEW);
        $missing = [];

        foreach ($this->configManager->get()['themes'] ?? [] as $theme => $definition) {
            $source = $definition['src'] . '/' . self::WEB_PATH . '/' . ConfigInterface::GENERATED_PATH;
            if (!$this->driver->isDirectory($source)) {
                continue;
            }

            $built = $this->relativePaths($source);
            foreach ($locales as $locale) {
                $target = $staticRoot . '/' . self::AREA . '/' . $theme . '/' . $locale
                    . '/' . ConfigInterface::GENERATED_PATH . '/';

                $absent = array_values(array_filter(
                    $built,
                    fn (string $file): bool => !$this->driver->isExists($target . $file)
                ));

                if ($absent !== []) {
                    $missing[$theme . '@' . $locale] = $absent;
                }
            }
        }

        return $missing;
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
