<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service\Csp;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Request\Http;
use MageObsidian\ModernFrontend\Model\Csp\BlockPolicyFrames;

class ResponseCacheability
{
    private const string PAGE_CACHE_TYPE = 'full_page';

    private const string ESI_ACTION = 'page_cache_block_esi';

    private ?bool $layoutCacheable = null;

    public function __construct(
        private readonly StateInterface $cacheState,
        private readonly Http $request,
        private readonly BlockPolicyFrames $frames
    ) {
    }

    public function recordLayout(bool $cacheable): void
    {
        $this->layoutCacheable ??= $cacheable;
    }

    public function mayBeStored(): bool
    {
        if ($this->frames->hasOpen()) {
            return true;
        }

        if (!$this->cacheState->isEnabled(self::PAGE_CACHE_TYPE)) {
            return false;
        }

        if (!$this->request->isGet() && !$this->request->isHead()) {
            return false;
        }

        return $this->layoutCacheable !== false || $this->request->getFullActionName() === self::ESI_ACTION;
    }
}
