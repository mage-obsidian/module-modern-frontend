<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use MageObsidian\ModernFrontend\Block\SectionPrefetchRuntime;
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

    private function render(SectionPrefetchRuntime $block): string
    {
        $method = new \ReflectionMethod($block, '_toHtml');

        return (string)$method->invoke($block);
    }

    private function buildBlock(mixed $sections): SectionPrefetchRuntime
    {
        return new SectionPrefetchRuntime(
            $this->createMock(Context::class),
            $this->createMock(\Magento\Framework\View\Helper\SecureHtmlRenderer::class),
            $this->createMock(\Magento\Framework\Module\Dir\Reader::class),
            ['sections' => $sections]
        );
    }
}
