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
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\Block\SectionPrefetchRuntime;
use MageObsidian\ModernFrontend\Service\RuntimeScriptReader;
use PHPUnit\Framework\TestCase;

class SectionPrefetchRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testKeepsOnlyNonEmptyUniqueSectionNames(): void
    {
        $block = $this->buildBlock(['obsidian-checkout', ' cart ', '', 'cart', null]);

        $this->assertSame(['obsidian-checkout', 'cart'], $block->getSections());
    }

    public function testSectionsIsEmptyWhenTheLayoutDeclaredNone(): void
    {
        $this->assertSame([], $this->buildBlock(null)->getSections());
        $this->assertSame([], $this->buildBlock('obsidian-checkout')->getSections());
        $this->assertSame([], $this->buildBlock([])->getSections());
    }

    public function testRendersNothingWithoutSections(): void
    {
        $this->assertSame('', $this->render($this->buildBlock([])));
    }

    public function testTheInlinedScriptCarriesNoLicenseHeader(): void
    {
        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->once())
            ->method('renderTag')
            ->with('script', [], $this->callback(
                static fn(string $content): bool => str_contains($content, '__MAGE_OBSIDIAN_SECTION_PREFETCH_CONFIG__')
                    && !str_contains($content, 'SPDX')
                    && !str_contains($content, 'This file is part of')
            ), false)
            ->willReturn('<script>…</script>');

        $this->assertSame('<script>…</script>', $this->render($this->buildBlock(['cart'], $renderer)));
    }

    private function render(SectionPrefetchRuntime $block): string
    {
        $method = new \ReflectionMethod($block, '_toHtml');

        return (string)$method->invoke($block);
    }

    private function buildBlock(mixed $sections, ?SecureHtmlRenderer $renderer = null): SectionPrefetchRuntime
    {
        $moduleReader = $this->createStub(Reader::class);
        $moduleReader->method('getModuleDir')->willReturn(__DIR__ . '/../../../view');

        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturn('https://example.test/customer/section/load/');
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);

        return new SectionPrefetchRuntime(
            $context,
            $renderer ?? $this->createStub(SecureHtmlRenderer::class),
            new RuntimeScriptReader($moduleReader),
            ['sections' => $sections]
        );
    }
}
