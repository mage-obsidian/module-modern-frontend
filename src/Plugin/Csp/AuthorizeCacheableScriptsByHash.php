<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Csp;

use InvalidArgumentException;
use Magento\Csp\Helper\InlineUtil;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\View\Helper\SecureHtmlRender\TagData;
use MageObsidian\ModernFrontend\Service\Csp\InlineScriptPolicy;
use MageObsidian\ModernFrontend\Service\Csp\ResponseCacheability;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;

class AuthorizeCacheableScriptsByHash
{
    private const string SCRIPT = 'script';

    private const string SCRIPT_POLICY = 'script-src';

    private const string SOURCE_ATTRIBUTE = 'src';

    private const string HASH_ALGORITHM = 'sha256';

    public function __construct(
        private readonly ObsidianThemeDetector $themeDetector,
        private readonly InlineScriptPolicy $inlineScriptPolicy,
        private readonly ResponseCacheability $cacheability,
        private readonly DynamicCollector $dynamicCollector
    ) {
    }

    public function aroundProcessTag(InlineUtil $subject, callable $proceed, TagData $tagData): TagData
    {
        $content = (string)$tagData->getContent();
        if ($tagData->getTag() !== self::SCRIPT
            || $content === ''
            || !$this->themeDetector->isActive()
            || $this->inlineScriptPolicy->allowsInlineScripts()
            || !$this->cacheability->mayBeStored()
        ) {
            return $proceed($tagData);
        }

        if (array_key_exists(self::SOURCE_ATTRIBUTE, $tagData->getAttributes())) {
            return $this->authorizedBySource($proceed, $tagData);
        }

        $this->dynamicCollector->add(
            new FetchPolicy(
                self::SCRIPT_POLICY,
                false,
                [],
                [],
                false,
                false,
                false,
                [],
                [base64_encode(hash(self::HASH_ALGORITHM, $content, true)) => self::HASH_ALGORITHM]
            )
        );

        return $tagData;
    }

    private function authorizedBySource(callable $proceed, TagData $tagData): TagData
    {
        try {
            $proceed(new TagData($tagData->getTag(), $tagData->getAttributes(), null, $tagData->isTextContent()));
        } catch (InvalidArgumentException) {
            return $tagData;
        }

        return $tagData;
    }
}
