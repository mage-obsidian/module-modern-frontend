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
use MageObsidian\ModernFrontend\Block\SectionDataRuntime;
use MageObsidian\ModernFrontend\Service\PageConfigRenderer;
use MageObsidian\ModernFrontend\ViewModel\SectionDataConfig;
use PHPUnit\Framework\TestCase;

class SectionDataRuntimeTest extends TestCase
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
            '<script type="application/json" data-mage-obsidian-config="sections">'
            . '{"lifetime":3600,"expirable":["cart"]}</script>',
            $this->render(['lifetime' => 3600, 'expirable' => ['cart']])
        );
    }

    public function testAValueCannotCloseTheBlock(): void
    {
        $html = $this->render(['lifetime' => 3600, 'expirable' => ['</script><script>alert(1)']]);

        $this->assertSame(1, substr_count($html, '</script>'));
    }

    private function render(array $config): string
    {
        $sectionDataConfig = $this->createStub(SectionDataConfig::class);
        $sectionDataConfig->method('getConfig')->willReturn($config);

        $block = new SectionDataRuntime(
            $this->createStub(Context::class),
            $sectionDataConfig,
            new PageConfigRenderer()
        );

        return (new \ReflectionMethod($block, '_toHtml'))->invoke($block);
    }
}
