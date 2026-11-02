<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Csp;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Request\Http;
use MageObsidian\ModernFrontend\Model\Csp\BlockPolicyFrames;
use MageObsidian\ModernFrontend\Service\Csp\ResponseCacheability;
use PHPUnit\Framework\TestCase;

class ResponseCacheabilityTest extends TestCase
{
    private BlockPolicyFrames $frames;

    protected function setUp(): void
    {
        if (!class_exists(Http::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
        $this->frames = new BlockPolicyFrames();
    }

    public function testACacheablePageMayBeStored(): void
    {
        $cacheability = $this->cacheability();
        $cacheability->recordLayout(true);

        $this->assertTrue($cacheability->mayBeStored());
    }

    public function testAResponseWhoseLayoutIsNotGeneratedYetCountsAsStorable(): void
    {
        $this->assertTrue($this->cacheability()->mayBeStored());
    }

    public function testNothingIsStoredWithThePageCacheDisabled(): void
    {
        $this->assertFalse($this->cacheability(pageCache: false)->mayBeStored());
    }

    public function testAPostIsNeverStored(): void
    {
        $this->assertFalse($this->cacheability(method: 'POST')->mayBeStored());
    }

    public function testAHeadRequestMayBeStored(): void
    {
        $this->assertTrue($this->cacheability(method: 'HEAD')->mayBeStored());
    }

    public function testAPageDeclaredNotCacheableIsNotStored(): void
    {
        $cacheability = $this->cacheability();
        $cacheability->recordLayout(false);

        $this->assertFalse($cacheability->mayBeStored());
    }

    public function testAnEsiBlockMayBeStoredWhateverItsLayoutSays(): void
    {
        $cacheability = $this->cacheability(action: 'page_cache_block_esi');
        $cacheability->recordLayout(false);

        $this->assertTrue($cacheability->mayBeStored());
    }

    public function testThePageLayoutDecidesAndLaterLayoutsDoNot(): void
    {
        $cacheability = $this->cacheability();
        $cacheability->recordLayout(false);
        $cacheability->recordLayout(true);

        $this->assertFalse($cacheability->mayBeStored());
    }

    public function testMarkupRenderedIntoTheBlockCacheMayBeStoredOnAnyResponse(): void
    {
        $cacheability = $this->cacheability(pageCache: false, method: 'POST');
        $cacheability->recordLayout(false);
        $this->frames->open('block_html_cart');

        $this->assertTrue($cacheability->mayBeStored());

        $this->frames->close('block_html_cart');

        $this->assertFalse($cacheability->mayBeStored());
    }

    private function cacheability(
        bool $pageCache = true,
        string $method = 'GET',
        string $action = 'catalog_category_view'
    ): ResponseCacheability {
        $cacheState = $this->createStub(StateInterface::class);
        $cacheState->method('isEnabled')->willReturnCallback(
            static fn(string $type): bool => $type === 'full_page' && $pageCache
        );

        $request = $this->createStub(Http::class);
        $request->method('isGet')->willReturn($method === 'GET');
        $request->method('isHead')->willReturn($method === 'HEAD');
        $request->method('getFullActionName')->willReturn($action);

        return new ResponseCacheability($cacheState, $request, $this->frames);
    }
}
