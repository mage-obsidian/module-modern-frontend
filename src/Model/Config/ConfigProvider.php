<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Model\Config;

use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

readonly class ConfigProvider implements ArgumentInterface
{
    const string ROOT_PATH = 'mage-obsidian/';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private State $state
    ) {
    }

    public function isHmrEnabled(): bool
    {
        if ($this->state->getMode() === State::MODE_PRODUCTION) {
            return false;
        }
        return (bool)$this->scopeConfig->getValue(
            self::ROOT_PATH . 'hmr/enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getViteGeneratedPath(): string
    {
        if ($this->isHmrEnabled()) {
            return ConfigInterface::VITE_GENERATED_PATH;
        }
        return ConfigInterface::GENERATED_PATH;
    }
}
