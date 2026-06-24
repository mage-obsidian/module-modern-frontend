<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\ModernFrontend\Service\CriticalCssProvider;

/**
 * Feeds the critical-CSS head block and gates the stylesheet defer.
 *
 * The critical CSS for the most specific active layout handle is inlined; the
 * Beasties-extracted critical already carries the `[v-cloak]` rule (it lives in
 * the sheet), so the stub is only a fallback when no critical was generated for
 * the request. {@see hasCriticalCss} keeps the inline and the defer coupled to
 * the same `dev/css/use_css_critical_path` flag, so disabling it cleanly reverts
 * to the render-blocking stylesheet with no FOUC.
 */
class CriticalCss implements ArgumentInterface
{
    private const VCLOAK = '[v-cloak]{display:none!important}';
    private const FLAG = 'dev/css/use_css_critical_path';

    private ?string $resolved = null;

    public function __construct(
        private readonly CriticalCssProvider $provider,
        private readonly LayoutInterface $layout,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getCriticalCssData(): string
    {
        $critical = $this->resolveCritical();

        return $critical !== '' ? $critical : self::VCLOAK;
    }

    public function hasCriticalCss(): bool
    {
        return $this->isEnabled() && $this->resolveCritical() !== '';
    }

    private function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::FLAG, ScopeInterface::SCOPE_STORE);
    }

    private function resolveCritical(): string
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        foreach ($this->activeHandles() as $handle) {
            $css = $this->provider->getCriticalCss($handle);
            if ($css !== '') {
                return $this->resolved = $css;
            }
        }

        return $this->resolved = '';
    }

    /**
     * Active layout handles, most specific first.
     *
     * @return string[]
     */
    private function activeHandles(): array
    {
        return array_reverse($this->layout->getUpdate()->getHandles());
    }
}
