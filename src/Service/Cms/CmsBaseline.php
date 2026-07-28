<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Cms;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Asset\File\NotFoundException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Design\ThemeInterface;
use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The class names a theme's build already covers.
 *
 * The engine writes this next to the stylesheet it produced, so reading it here
 * answers "what is already in the CSS I am serving" without parsing the CSS.
 * Everything the content uses beyond this list is the delta.
 *
 * Read from the theme source like the Vite manifest, so it works in any deploy
 * mode. Missing means the theme was built before this existed, which reads as an
 * empty baseline: the delta then covers every class, which is correct but
 * wasteful, so the doctor says so instead of letting it pass unnoticed.
 */
class CmsBaseline
{
    /** @var array<string, array{classes: string[], present: bool}> */
    private array $cache = [];

    public function __construct(
        private readonly Repository $assetRepository,
        private readonly ConfigProvider $configProvider,
        private readonly State $appState,
        private readonly File $fileDriver,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return string[]
     */
    public function read(?ThemeInterface $theme = null): array
    {
        return $this->load($theme)['classes'];
    }

    /**
     * Whether a baseline was actually found, as opposed to defaulted to empty.
     */
    public function exists(?ThemeInterface $theme = null): bool
    {
        return $this->load($theme)['present'];
    }

    /**
     * @return array{classes: string[], present: bool}
     */
    private function load(?ThemeInterface $theme): array
    {
        $key = $theme?->getCode() ?? '';
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $this->cache[$key] = ['classes' => [], 'present' => false];

        try {
            $fileId = $this->configProvider->getViteGeneratedPath() . '/' . ConfigInterface::CMS_BASELINE_FILE;
            // Emulated rather than assumed: a cron job, an observer on an admin
            // save and `bin/magento` all reach this with a different area — or
            // none at all, which makes asset resolution throw outright.
            $source = $this->appState->emulateAreaCode(
                Area::AREA_FRONTEND,
                function () use ($fileId, $theme): string {
                    $params = $theme ? ['area' => Area::AREA_FRONTEND, 'themeModel' => $theme] : [];

                    return $this->assetRepository->createAsset($fileId, $params)->getSourceFile();
                }
            );
            if (!$this->fileDriver->isExists($source)) {
                return $this->cache[$key];
            }
            $decoded = json_decode($this->fileDriver->fileGetContents($source), true);
            if (!is_array($decoded)) {
                return $this->cache[$key];
            }

            return $this->cache[$key] = [
                'classes' => array_values(array_filter($decoded, 'is_string')),
                'present' => true,
            ];
        } catch (NotFoundException) {
            // theme built before the baseline existed
        } catch (Throwable $e) {
            $this->logger->warning('MageObsidian: could not read the CMS class baseline: ' . $e->getMessage());
        }

        return $this->cache[$key];
    }
}
