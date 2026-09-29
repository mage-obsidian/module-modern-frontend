<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Model\Deploy;

use Magento\Framework\Exception\LocalizedException;

class ViteBuildPreflight
{
    /**
     * @param string[] $outputDirs
     * @throws LocalizedException
     */
    public function assertWritable(string $viteDir, array $outputDirs): void
    {
        foreach ([$viteDir, ...$outputDirs] as $dir) {
            $target = self::nearestExisting($dir);
            if (!is_writable($target)) {
                throw new LocalizedException(__(
                    'Cannot build Vite assets: "%1" is not writable. Run the build in a phase that can write '
                    . 'to the code tree (on Adobe Commerce Cloud, the build phase with SCD_ON_BUILD).',
                    $target
                ));
            }
        }
    }

    public static function nearestExisting(string $path): string
    {
        while (!file_exists($path) && dirname($path) !== $path) {
            $path = dirname($path);
        }

        return $path;
    }
}
