<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Vue;

use MageObsidian\ModernFrontend\Service\Vue\IslandMarkup;
use PHPUnit\Framework\TestCase;

class IslandMarkupTest extends TestCase
{
    private function markup(): IslandMarkup
    {
        return new IslandMarkup();
    }

    public function testListWrapsMarkupInVueFragmentAnchors(): void
    {
        $this->assertSame(
            '<!--[--><span>28</span><span>29</span><!--]-->',
            $this->markup()->list('<span>28</span><span>29</span>')
        );
    }

    public function testListTrimsTheIndentationOfTheCapturedBlock(): void
    {
        $this->assertSame(
            '<!--[--><span>28</span><!--]-->',
            $this->markup()->list("\n        <span>28</span>\n    ")
        );
    }

    public function testListOfAnEmptyLoopStillEmitsBothAnchors(): void
    {
        // Vue renders an empty v-for as the two anchors with nothing between
        // them; dropping them would leave hydration without its boundaries.
        $this->assertSame('<!--[--><!--]-->', $this->markup()->list(''));
    }

    public function testIfEmitsTheMarkupWhenTheConditionHolds(): void
    {
        $this->assertSame('<b>sale</b>', $this->markup()->if(true, "\n  <b>sale</b>\n"));
    }

    public function testIfEmitsVuesPlaceholderWhenTheConditionFails(): void
    {
        $this->assertSame('<!---->', $this->markup()->if(false, '<b>sale</b>'));
    }
}
