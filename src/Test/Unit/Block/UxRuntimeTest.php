<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\Block\UxRuntime;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Service\PageConfigRenderer;
use PHPUnit\Framework\TestCase;

class UxRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheConfigAsADataBlock(): void
    {
        $this->assertSame(
            '<script type="application/json" data-mage-obsidian-config="ux">'
            . '{"optimistic":true,"summaryCountsQty":true}</script>',
            $this->render(true, true)
        );
    }

    public function testCarriesBothFlagsVerbatim(): void
    {
        $html = $this->render(false, false);

        $this->assertStringContainsString('"optimistic":false', $html);
        $this->assertStringContainsString('"summaryCountsQty":false', $html);
    }

    public function testCarriesNothingButBooleansSoNoValueCanCloseTheBlock(): void
    {
        $this->assertSame(1, substr_count($this->render(true, false), '</script>'));
    }

    private function render(bool $optimistic, bool $countsQty): string
    {
        $configProvider = $this->createStub(ConfigProvider::class);
        $configProvider->method('isOptimisticUiEnabled')->willReturn($optimistic);
        $configProvider->method('doesCartSummaryCountQty')->willReturn($countsQty);

        $block = new UxRuntime($this->createStub(Context::class), $configProvider, new PageConfigRenderer());

        return (new \ReflectionMethod($block, '_toHtml'))->invoke($block);
    }
}
