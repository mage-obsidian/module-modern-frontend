<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Framework\View\Asset\PreProcessor;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\File\FallbackContext;
use MageObsidian\ModernFrontend\Service\ConfigManager;
use Magento\Framework\View\Asset\PreProcessor\Chain;
use Magento\Framework\View\Asset\PreProcessor\Pool;

class PoolPlugin
{
    public const array FILE_EXCLUDE = [
        'css',
        'less',
        'scss'
    ];

    /**
     * PoolPlugin constructor.
     *
     * @param ConfigManager $configManager
     */
    public function __construct(
        private readonly ConfigManager $configManager
    ) {
    }

    /**
     * Around process method.
     *
     * @param Pool $subject
     * @param callable $proceed
     * @param Chain $chain
     *
     * @return void
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function aroundProcess(
        Pool $subject,
        callable $proceed,
        Chain $chain
    ): void {
        $type = $chain->getTargetContentType();
        $asset = $chain->getAsset();
        /** @var FallbackContext $context */
        $context = $asset->getContext();
        if (in_array($type, self::FILE_EXCLUDE) && $this->configManager->isThemeEnabled($context->getThemePath())) {
            return;
        }
        $proceed($chain);
    }
}
