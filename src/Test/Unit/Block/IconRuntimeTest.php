<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\Block\IconRuntime;
use MageObsidian\ModernFrontend\Service\PageConfigRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\TestCase;

class IconRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheIconBaseAsADataBlock(): void
    {
        $this->assertSame(
            '<script type="application/json" data-mage-obsidian-config="icons">'
            . '{"baseUrl":"https:\\/\\/shop.test\\/icons\\/"}</script>',
            $this->render('https://shop.test/icons/')
        );
    }

    public function testAValueCannotCloseTheBlock(): void
    {
        $this->assertSame(1, substr_count($this->render('</script><script>alert(1)'), '</script>'));
    }

    private function render(string $baseUrl): string
    {
        $resolver = $this->createStub(ViteResolver::class);
        $resolver->method('getIconBaseUrl')->willReturn($baseUrl);

        $block = new IconRuntime($this->createStub(Context::class), $resolver, new PageConfigRenderer());

        return (new \ReflectionMethod($block, '_toHtml'))->invoke($block);
    }
}
