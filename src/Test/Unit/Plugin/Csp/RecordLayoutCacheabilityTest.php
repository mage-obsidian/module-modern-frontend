<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Csp;

use Magento\Framework\View\Layout;
use MageObsidian\ModernFrontend\Plugin\Csp\RecordLayoutCacheability;
use MageObsidian\ModernFrontend\Service\Csp\ResponseCacheability;
use PHPUnit\Framework\TestCase;

class RecordLayoutCacheabilityTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Layout::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testRecordsWhetherTheGeneratedLayoutIsCacheable(): void
    {
        $layout = $this->createStub(Layout::class);
        $layout->method('isCacheable')->willReturn(false);
        $cacheability = $this->createMock(ResponseCacheability::class);
        $cacheability->expects($this->once())->method('recordLayout')->with(false);

        (new RecordLayoutCacheability($cacheability))->afterGenerateElements($layout, null);
    }

    public function testHandsTheResultBackUntouched(): void
    {
        $layout = $this->createStub(Layout::class);
        $layout->method('isCacheable')->willReturn(true);

        $this->assertSame(
            'result',
            (new RecordLayoutCacheability($this->createStub(ResponseCacheability::class)))
                ->afterGenerateElements($layout, 'result')
        );
    }
}
