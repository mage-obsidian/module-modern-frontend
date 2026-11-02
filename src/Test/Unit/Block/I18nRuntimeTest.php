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
use MageObsidian\ModernFrontend\Block\I18nRuntime;
use MageObsidian\ModernFrontend\Service\PageConfigRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\TestCase;

class I18nRuntimeTest extends TestCase
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
            '<script type="application/json" data-mage-obsidian-config="i18n">'
            . '{"locale":"en_US","dictionaryUrl":"https:\\/\\/shop.test\\/js-translation.json"}</script>',
            $this->render(['locale' => 'en_US', 'dictionaryUrl' => 'https://shop.test/js-translation.json'])
        );
    }

    public function testAValueCannotCloseTheBlock(): void
    {
        $html = $this->render(['locale' => '</script><script>alert(1)', 'dictionaryUrl' => null]);

        $this->assertSame(1, substr_count($html, '</script>'));
    }

    private function render(array $config): string
    {
        $resolver = $this->createStub(ViteResolver::class);
        $resolver->method('getI18nRuntimeConfig')->willReturn($config);

        $block = new I18nRuntime($this->createStub(Context::class), $resolver, new PageConfigRenderer());

        return (new \ReflectionMethod($block, '_toHtml'))->invoke($block);
    }
}
