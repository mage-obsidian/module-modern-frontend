<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Dev;

use MageObsidian\ModernFrontend\Service\Dev\ModeAdvisor;
use PHPUnit\Framework\TestCase;

class ModeAdvisorTest extends TestCase
{
    public function testDeveloperModePointsToTheDevServer(): void
    {
        $messages = ModeAdvisor::messagesForMode(ModeAdvisor::MODE_DEVELOPER);

        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('mage-obsidian:frontend:dev --up', implode("\n", $messages));
    }

    public function testDefaultModeMentionsStaticAssets(): void
    {
        $messages = ModeAdvisor::messagesForMode(ModeAdvisor::MODE_DEFAULT);

        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('static', implode("\n", $messages));
    }

    public function testProductionModeMentionsHmrForcedOff(): void
    {
        $messages = ModeAdvisor::messagesForMode(ModeAdvisor::MODE_PRODUCTION);

        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('forced off', implode("\n", $messages));
    }

    public function testUnknownModeYieldsNoAdvice(): void
    {
        $this->assertSame([], ModeAdvisor::messagesForMode('whatever'));
    }
}
