<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Plugin\Csp;

use Magento\Csp\Api\Data\ModeConfiguredInterface;
use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Model\Mode\Data\ModeConfiguredFactory;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;

/**
 * While the Vite dev server (HMR) is active, the storefront depends on inline
 * scripts and cross-origin assets that Magento's nonce cannot cover: Vite
 * injects its own inline HMR client / style tags, resolves the ES module graph
 * from the dev-server host, and opens a websocket for hot updates. None of that
 * carries our nonce, and `unsafe-inline` is ignored once a nonce is present in
 * script-src — so on pages that enforce CSP (checkout, multishipping, sales)
 * the dev assets are blocked and the page renders unstyled.
 *
 * Forcing report-only whenever HMR is enabled makes those pages behave like the
 * rest of the storefront (already report-only by default), so violations are
 * reported but not enforced and Vite works. Production is untouched:
 * ConfigProvider::isHmrEnabled() is always false in production mode, and this
 * plugin is registered for the frontend area only.
 */
class ForceReportOnlyInHmr
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ModeConfiguredFactory $modeConfiguredFactory
    ) {
    }

    /**
     * @param ModeConfigManagerInterface $subject
     * @param ModeConfiguredInterface $result
     * @return ModeConfiguredInterface
     */
    public function afterGetConfigured(
        ModeConfigManagerInterface $subject,
        ModeConfiguredInterface $result
    ): ModeConfiguredInterface {
        if ($result->isReportOnly() || !$this->configProvider->isHmrEnabled()) {
            return $result;
        }

        return $this->modeConfiguredFactory->create([
            'reportOnly' => true,
            'reportUri' => $result->getReportUri(),
        ]);
    }
}
