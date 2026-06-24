<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Plugin\View\Result;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Theme\Controller\Result\AsyncCssPlugin;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Skips Magento's native async-CSS rewrite while a MageObsidian theme is active.
 *
 * The native AsyncCssPlugin reacts to `dev/css/use_css_critical_path` by moving
 * *every* `<link rel="stylesheet">` in the response to a `media="print"` swap —
 * a global defer that flashes unstyled content on any page without inlined
 * critical CSS. We reuse that same flag, but defer per page in head_additional
 * (only when critical exists for the handle), so the native global pass must not
 * run for our themes. Legacy/admin themes keep the native behaviour untouched.
 */
class AsyncCssThemePlugin
{
    public function __construct(
        private readonly DesignInterface $design,
        private readonly ConfigManagerInterface $configManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function aroundAfterRenderResult(
        AsyncCssPlugin $subject,
        callable $proceed,
        Layout $renderSubject,
        Layout $result,
        ResponseInterface $httpResponse
    ): Layout {
        if ($this->isObsidianTheme()) {
            return $result;
        }

        return $proceed($renderSubject, $result, $httpResponse);
    }

    /**
     * Whether the active design theme opted into the MageObsidian pipeline.
     *
     * Any failure degrades to "not Obsidian" so the native async-CSS behaviour
     * is left in place rather than masked.
     *
     * @return bool
     */
    private function isObsidianTheme(): bool
    {
        try {
            $themeCode = (string)$this->design->getDesignTheme()->getCode();
            return $themeCode !== '' && $this->configManager->isThemeEnabled($themeCode);
        } catch (Throwable $e) {
            $this->logger->warning(
                'MageObsidian: could not resolve the active theme for async-css; '
                . 'leaving native behaviour in place: ' . $e->getMessage()
            );
            return false;
        }
    }
}
