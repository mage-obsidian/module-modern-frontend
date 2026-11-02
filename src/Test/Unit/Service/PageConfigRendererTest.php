<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service;

use InvalidArgumentException;
use MageObsidian\ModernFrontend\Service\PageConfigRenderer;
use PHPUnit\Framework\TestCase;

class PageConfigRendererTest extends TestCase
{
    public function testRendersADataBlockTheBrowserNeverExecutes(): void
    {
        $html = (new PageConfigRenderer())->render(PageConfigRenderer::UX, ['optimistic' => true]);

        $this->assertSame(
            '<script type="application/json" data-mage-obsidian-config="ux">{"optimistic":true}</script>',
            $html
        );
    }

    public function testAValueCannotCloseTheBlock(): void
    {
        $html = (new PageConfigRenderer())->render(PageConfigRenderer::I18N, ['locale' => '</script><script>alert(1)']);

        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function testEscapesQuotesAndAmpersandsInValues(): void
    {
        $html = (new PageConfigRenderer())->render(PageConfigRenderer::ICONS, ['baseUrl' => "a'b\"c&d"]);

        $this->assertStringContainsString('"a\u0027b\u0022c\u0026d"', $html);
    }

    public function testRefusesANameThatIsNotASimpleIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PageConfigRenderer())->render('ux" onload="x', []);
    }

    public function testNamesMatchTheOnesTheRuntimeReads(): void
    {
        $this->assertSame(
            ['i18n', 'icons', 'prepaint', 'section-prefetch-config', 'sections', 'ux'],
            [
                PageConfigRenderer::I18N,
                PageConfigRenderer::ICONS,
                PageConfigRenderer::PREPAINT,
                PageConfigRenderer::SECTION_PREFETCH,
                PageConfigRenderer::SECTIONS,
                PageConfigRenderer::UX,
            ]
        );
    }
}
