<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageObsidian\ModernFrontend\Service\Cms\IslandRegistry;
use MageObsidian\ModernFrontend\Service\IslandManifest;

/**
 * The islands an author can actually pick: registered as CMS-safe *and* present
 * in the current theme's build. Offering one that was never built would hand the
 * browser a marker pointing at a 404.
 */
class CmsIsland implements OptionSourceInterface
{
    public function __construct(
        private readonly IslandRegistry $registry,
        private readonly IslandManifest $manifest
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $registered = $this->registry->getAll();
        // No manifest means no answer, not an empty one: under HMR assets come
        // from the dev server and nothing was built to read.
        $built = $this->manifest->getBuiltComponents();
        if ($built !== []) {
            $registered = array_intersect_key($registered, array_flip($built));
        }

        $options = [];
        foreach ($registered as $component => $island) {
            $options[] = ['value' => $component, 'label' => $island['label']];
        }

        return $options;
    }
}
