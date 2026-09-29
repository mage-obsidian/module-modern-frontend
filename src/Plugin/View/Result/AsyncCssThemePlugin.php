<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\View\Result;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Theme\Controller\Result\AsyncCssPlugin;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;

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
        private readonly ObsidianThemeDetector $themeDetector
    ) {
    }

    public function aroundAfterRenderResult(
        AsyncCssPlugin $subject,
        callable $proceed,
        Layout $renderSubject,
        Layout $result,
        ResponseInterface $httpResponse
    ): Layout {
        if ($this->themeDetector->isActive()) {
            return $result;
        }

        return $proceed($renderSubject, $result, $httpResponse);
    }
}
