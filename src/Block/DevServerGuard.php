<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;

/**
 * Client-side developer guard: when HMR is enabled (developer/default mode) the
 * storefront depends on the Vite dev server. This injects a tiny script that
 * probes the dev server and, if it is unreachable, shows an actionable banner so
 * the developer never faces silent broken assets / cryptic 502s.
 *
 * Probes `/@vite/client` (served by the dev server) rather than `/__vite_ping`,
 * which Vite 8 no longer exposes (it 404s even when the server is up). Renders
 * nothing when HMR is disabled or in production.
 */
class DevServerGuard extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly ConfigProvider $configProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->configProvider->isHmrEnabled()) {
            return '';
        }

        return <<<HTML
            <script>
            (function () {
                fetch('/@vite/client', { method: 'GET', cache: 'no-store' })
                    .then(function (response) { if (!response.ok) { throw new Error('status ' + response.status); } })
                    .catch(function () {
                        if (document.getElementById('mage-obsidian-dev-guard')) { return; }
                        var banner = document.createElement('div');
                        banner.id = 'mage-obsidian-dev-guard';
                        banner.setAttribute('role', 'alert');
                        banner.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:2147483647;'
                            + 'background:#7f1d1d;color:#fff;font:14px/1.5 system-ui,-apple-system,sans-serif;'
                            + 'padding:10px 16px;text-align:center;box-shadow:0 -2px 8px rgba(0,0,0,.35)';
                        banner.innerHTML = 'MageObsidian — the Vite dev server is not responding, so your '
                            + 'changes will not be reflected. Start it '
                            + '(<code>mage-obsidian:build-themes --dev-server</code>), run '
                            + '<code>bin/magento mage-obsidian:frontend:doctor</code>, or disable HMR to use built assets.';
                        document.body.appendChild(banner);
                    });
            })();
            </script>
        HTML;
    }
}
