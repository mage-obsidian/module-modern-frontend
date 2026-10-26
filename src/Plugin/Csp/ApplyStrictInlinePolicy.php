<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Csp;

use Magento\Csp\Api\Data\PolicyInterface;
use Magento\Csp\Model\Collector\ConfigCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;

class ApplyStrictInlinePolicy
{
    private const string SCRIPTS = 'script-src';

    private const string STYLES = 'style-src';

    private const string PAGE_STYLES_PATH = 'csp/policies/storefront_%s/styles/inline';

    /**
     * @param string[] $inlineStylePages
     */
    public function __construct(
        private readonly ObsidianThemeDetector $themeDetector,
        private readonly Http $request,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly array $inlineStylePages = []
    ) {
    }

    /**
     * @param PolicyInterface[] $result
     *
     * @return PolicyInterface[]
     */
    public function afterCollect(ConfigCollector $subject, array $result): array
    {
        if (!$this->themeDetector->isActive()) {
            return $result;
        }

        $inline = [self::SCRIPTS => false, self::STYLES => $this->inlineStylesForPage()];
        foreach ($result as $key => $policy) {
            if (!$policy instanceof FetchPolicy) {
                continue;
            }
            $allowed = $inline[$policy->getId()] ?? null;
            if ($allowed !== null && $allowed !== $policy->isInlineAllowed()) {
                $result[$key] = $this->withInline($policy, $allowed);
            }
        }

        return $result;
    }

    private function inlineStylesForPage(): ?bool
    {
        $page = (string)$this->request->getFullActionName();
        if (!in_array($page, $this->inlineStylePages, true)) {
            return false;
        }

        $configured = $this->scopeConfig->getValue(
            sprintf(self::PAGE_STYLES_PATH, $page),
            ScopeInterface::SCOPE_STORE
        );

        return $configured === null ? true : null;
    }

    private function withInline(FetchPolicy $policy, bool $inline): FetchPolicy
    {
        return new FetchPolicy(
            $policy->getId(),
            $policy->isNoneAllowed(),
            $policy->getHostSources(),
            $policy->getSchemeSources(),
            $policy->isSelfAllowed(),
            $inline,
            $policy->isEvalAllowed(),
            $policy->getNonceValues(),
            $policy->getHashes(),
            $policy->isDynamicAllowed(),
            $policy->areEventHandlersAllowed()
        );
    }
}
