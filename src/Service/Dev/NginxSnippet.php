<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Dev;

/**
 * Renders the nginx location blocks that proxy Vite's asset endpoints and a
 * theme's `vite_generated/` paths to the dev server for live HMR.
 *
 * Emitted, never applied: Magento cannot reload nginx and not every deployment
 * uses nginx, so the developer pastes this into their own server block. Pure so
 * the generated config is unit testable from the host:port alone.
 */
final class NginxSnippet
{
    /**
     * @return string nginx config text for the given dev server upstream
     */
    public static function render(string $host, string $port): string
    {
        $upstream = sprintf('http://%s:%s', $host, $port);

        return <<<NGINX
        # MageObsidian — Vite dev server proxy.
        # Place these server-level locations BEFORE the Magento include so they win
        # over Magento's /static/ prefix. The upstream is the dev server configured
        # in Magento (Stores > Configuration > MageObsidian > Frontend). Requires HMR
        # enabled (bin/magento mage-obsidian:frontend:hmr --enable) and the dev server
        # running (bin/magento mage-obsidian:frontend:dev --start --theme=<Vendor/theme>).

        location ~* ^/(?:@fs|@id|@vite|@react-refresh|node_modules|__vite_ping|\.precompiled) {
            set \$vite_upstream $upstream;
            proxy_set_header Host \$host;
            proxy_http_version 1.1;
            proxy_set_header Upgrade \$http_upgrade;
            proxy_set_header Connection "upgrade";
            proxy_pass \$vite_upstream;
        }

        # Matches both signed (/static/version<ts>/frontend/...) and unsigned
        # (/static/frontend/...) Magento static URLs — everything under
        # vite_generated/ is forwarded to the dev server.
        location ~* ^/static/.+/vite_generated/(.*)\$ {
            set \$vite_upstream $upstream;
            proxy_set_header Host \$host;
            proxy_http_version 1.1;
            proxy_set_header Upgrade \$http_upgrade;
            proxy_set_header Connection "upgrade";
            proxy_pass \$vite_upstream;
        }
        NGINX;
    }
}
