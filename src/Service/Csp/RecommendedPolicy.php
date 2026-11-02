<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Csp;

use Magento\Csp\Api\Data\PolicyInterface;
use Magento\Csp\Model\Policy\FetchPolicy;

class RecommendedPolicy
{
    private const array INLINE_DIRECTIVES = ['script-src', 'style-src', 'default-src'];

    private const string STYLES = 'style-src';

    private const string BASE_URI = 'base-uri';

    private const string OBJECT_SRC = 'object-src';

    public function __construct(
        private readonly bool $enabled = true
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function apply(array $policies, bool $keepInlineStyles): array
    {
        $result = [];
        foreach ($policies as $policy) {
            if (in_array($policy->getId(), [self::BASE_URI, self::OBJECT_SRC], true)) {
                continue;
            }
            $result[] = $policy instanceof FetchPolicy ? $this->harden($policy, $keepInlineStyles) : $policy;
        }
        $result[] = new FetchPolicy(self::BASE_URI, false, [], [], true);
        $result[] = new FetchPolicy(self::OBJECT_SRC, true);

        return $result;
    }

    private function harden(FetchPolicy $policy, bool $keepInlineStyles): PolicyInterface
    {
        $inline = $policy->isInlineAllowed() && !$this->forbidsInline($policy->getId(), $keepInlineStyles);
        if ($inline === $policy->isInlineAllowed() && !$policy->isEvalAllowed()) {
            return $policy;
        }

        return new FetchPolicy(
            $policy->getId(),
            $policy->isNoneAllowed(),
            $policy->getHostSources(),
            $policy->getSchemeSources(),
            $policy->isSelfAllowed(),
            $inline,
            false,
            $policy->getNonceValues(),
            $policy->getHashes(),
            $policy->isDynamicAllowed(),
            $policy->areEventHandlersAllowed()
        );
    }

    private function forbidsInline(string $id, bool $keepInlineStyles): bool
    {
        return in_array($id, self::INLINE_DIRECTIVES, true) && !($id === self::STYLES && $keepInlineStyles);
    }
}
