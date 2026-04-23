<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageObsidian\ModernFrontend\Model\Image\ImageDimensions;
use MageObsidian\ModernFrontend\Model\Image\ImageRenderer;
use Throwable;

/**
 * Template-facing API for rendering Core-Web-Vitals-friendly images, from both
 * `.phtml` (this ViewModel) and `.twig` (the `image` helper).
 *
 * Accepts either a ready URL or a `Vendor_Module::path` asset id. For an asset,
 * it resolves the URL and — when the caller omits both dimensions — auto-detects
 * the intrinsic size from the source file so the markup still reserves space
 * (no layout shift) without the author hand-measuring every image.
 */
class Image implements ArgumentInterface
{
    public function __construct(
        private readonly Repository $assetRepository,
        private readonly RequestInterface $request,
        private readonly ImageRenderer $renderer,
        private readonly ImageDimensions $dimensions
    ) {
    }

    /**
     * @param string $src A URL, or a `Vendor_Module::path` asset id.
     * @param array<string,mixed> $options See {@see ImageRenderer::render()}.
     *
     * @return string
     */
    public function render(string $src, array $options = []): string
    {
        if (str_contains($src, '::')) {
            return $this->renderAsset($src, $options);
        }

        return $this->renderer->render($src, $options);
    }

    /**
     * @param array<string,mixed> $options
     */
    private function renderAsset(string $fileId, array $options): string
    {
        $url = $this->assetRepository->getUrlWithParams(
            $fileId,
            ['_secure' => $this->request->isSecure()]
        );

        if (!isset($options['width']) && !isset($options['height'])) {
            $detected = $this->detectDimensions($fileId);
            if ($detected !== null) {
                $options['width'] = $detected[0];
                $options['height'] = $detected[1];
            }
        }

        return $this->renderer->render($url, $options);
    }

    /**
     * @return array{0:int, 1:int}|null
     */
    private function detectDimensions(string $fileId): ?array
    {
        try {
            $sourceFile = $this->assetRepository->createAsset($fileId)->getSourceFile();
        } catch (Throwable) {
            return null;
        }

        return $this->dimensions->read($sourceFile);
    }
}
