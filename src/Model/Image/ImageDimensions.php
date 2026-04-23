<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\Image;

/**
 * Thin, injectable seam around `getimagesize()` so the consuming ViewModel can
 * auto-detect an asset's intrinsic dimensions without depending on the global
 * function directly (and so it can be stubbed in tests).
 */
class ImageDimensions
{
    /**
     * Read the intrinsic pixel size of a local image file.
     *
     * @param string $absolutePath Absolute filesystem path to the image.
     *
     * @return array{0:int, 1:int}|null `[width, height]`, or null when the file
     *         is missing/unreadable or not a recognizable image.
     */
    public function read(string $absolutePath): ?array
    {
        if ($absolutePath === '' || !is_file($absolutePath)) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }

        return [(int)$info[0], (int)$info[1]];
    }
}
