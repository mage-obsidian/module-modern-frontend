<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\Config;

use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;
use MageObsidian\ModernFrontend\Service\Dev\ViteEnvFile;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ArgumentInterface
{
    public const string ROOT_PATH = 'mage_obsidian/';
    public const string HMR_ENABLED = self::ROOT_PATH . 'hmr/enabled';
    public const string DEV_SERVER_PATH = self::ROOT_PATH . 'dev_server/';
    public const string DEV_SERVER_HOST = self::DEV_SERVER_PATH . 'host';
    public const string DEV_SERVER_PORT = self::DEV_SERVER_PATH . 'port';
    public const string DEV_SERVER_SECURE = self::DEV_SERVER_PATH . 'secure';
    public const string DEV_SERVER_HMR_PATH = self::DEV_SERVER_PATH . 'hmr_path';
    public const string DEV_SERVER_PUBLIC_HOST = self::DEV_SERVER_PATH . 'public_host';
    public const string DEV_SERVER_ALLOWED_HOSTS = self::DEV_SERVER_PATH . 'allowed_hosts';
    public const string SEO_PATH = self::ROOT_PATH . 'seo/';
    public const string STRUCTURED_DATA_ENABLED = self::SEO_PATH . 'structured_data_enabled';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param State $state
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly State $state
    ) {
    }

    /**
     * Get the value of HMR enabled configuration.
     *
     * @return bool
     */
    public function isHmrEnabled(): bool
    {
        if ($this->state->getMode() === State::MODE_PRODUCTION) {
            return false;
        }
        return (bool)$this->scopeConfig->getValue(self::HMR_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Whether the storefront should auto-emit schema.org structured data.
     *
     * On by default; can be turned off per store (e.g. when another SEO
     * extension already emits JSON-LD, to avoid duplicate structured data).
     *
     * @return bool
     */
    public function isStructuredDataEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::STRUCTURED_DATA_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get path to the generated files.
     *
     * @return string
     */
    public function getViteGeneratedPath(): string
    {
        if ($this->isHmrEnabled()) {
            return ConfigInterface::VITE_GENERATED_PATH;
        }
        return ConfigInterface::GENERATED_PATH;
    }

    /**
     * Vite dev server config read from Magento (the single source of truth for
     * the JS harness). Resolved at default scope: the dev server is a developer
     * environment concern, not a per-store setting.
     */
    public function getDevServerHost(): string
    {
        return (string)$this->scopeConfig->getValue(self::DEV_SERVER_HOST);
    }

    public function getDevServerPort(): string
    {
        return (string)$this->scopeConfig->getValue(self::DEV_SERVER_PORT);
    }

    public function isDevServerSecure(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::DEV_SERVER_SECURE);
    }

    public function getDevServerHmrPath(): string
    {
        return (string)$this->scopeConfig->getValue(self::DEV_SERVER_HMR_PATH);
    }

    public function getDevServerPublicHost(): string
    {
        return (string)$this->scopeConfig->getValue(self::DEV_SERVER_PUBLIC_HOST);
    }

    public function getDevServerAllowedHosts(): string
    {
        return (string)$this->scopeConfig->getValue(self::DEV_SERVER_ALLOWED_HOSTS);
    }

    /**
     * Resolve the full Vite env-var map from Magento config, ready to render
     * into the harness `.env`.
     *
     * @return array<string, string>
     */
    public function getViteEnvVars(): array
    {
        return ViteEnvFile::buildVars(
            $this->getDevServerHost(),
            $this->getDevServerPort(),
            $this->isDevServerSecure(),
            $this->getDevServerHmrPath(),
            $this->getDevServerPublicHost(),
            $this->getDevServerAllowedHosts()
        );
    }
}
