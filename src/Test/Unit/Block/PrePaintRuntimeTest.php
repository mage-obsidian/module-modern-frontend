<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Block;

use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\Block\PrePaintRuntime;
use MageObsidian\ModernFrontend\Service\RuntimeScriptReader;
use MageObsidian\ModernFrontend\ViewModel\PrePaintConfig;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PrePaintRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testTheInlinedScriptCarriesItsConfigAndNoLicenseHeader(): void
    {
        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->once())
            ->method('renderTag')
            ->with('script', [], $this->callback(
                static fn(string $content): bool => str_starts_with($content, 'window.__MAGE_OBSIDIAN_PREPAINT__ = {"storageKey":"mage-cache-storage"};')
                    && !str_contains($content, 'SPDX')
                    && !str_contains($content, 'This file is part of')
            ), false)
            ->willReturn('<script>…</script>');

        $this->assertSame('<script>…</script>', $this->render($renderer, ['storageKey' => 'mage-cache-storage']));
    }

    public function testRendersNothingWithoutConfig(): void
    {
        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->never())->method('renderTag');

        $this->assertSame('', $this->render($renderer, null));
    }

    private function render(SecureHtmlRenderer $renderer, ?array $config): string
    {
        $prePaintConfig = $this->createStub(PrePaintConfig::class);
        $prePaintConfig->method('isEmpty')->willReturn($config === null);
        $prePaintConfig->method('getConfig')->willReturn($config ?? []);

        $moduleReader = $this->createStub(Reader::class);
        $moduleReader->method('getModuleDir')->willReturn(__DIR__ . '/../../../view');

        $block = new PrePaintRuntime(
            $this->createStub(Context::class),
            $prePaintConfig,
            $renderer,
            new RuntimeScriptReader($moduleReader)
        );

        return (string)(new ReflectionMethod($block, '_toHtml'))->invoke($block);
    }
}
