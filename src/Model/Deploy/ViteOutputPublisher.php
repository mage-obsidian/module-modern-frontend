<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\Deploy;

use Magento\Framework\Filesystem\DriverInterface;
use Throwable;

/**
 * Puts the Vite build into `pub/static` for the files the deploy left behind.
 *
 * Magento's publisher skips a destination that already exists, so a rebuilt
 * bundle under a stable filename never replaces the one already published. This
 * copies those files itself, after the deploy rather than before it, so a run
 * that fails halfway leaves the storefront on the previous bundle instead of on
 * no bundle at all.
 */
class ViteOutputPublisher
{
    public function __construct(
        private readonly DriverInterface $driver
    ) {
    }

    /**
     * @return string[] the files that could not be published
     */
    public function publish(ViteOutputTarget $target): array
    {
        $failed = [];

        foreach ($target->files as $file) {
            $source = $target->sourceDirectory . '/' . $file;
            $destination = $target->targetDirectory . '/' . $file;

            try {
                $this->driver->createDirectory($this->driver->getParentDirectory($destination));
                if (!$this->driver->copy($source, $destination)) {
                    $failed[] = $file;
                }
            } catch (Throwable) {
                $failed[] = $file;
            }
        }

        return $failed;
    }
}
