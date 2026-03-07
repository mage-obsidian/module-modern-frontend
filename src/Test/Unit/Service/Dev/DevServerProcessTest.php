<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Dev;

use MageObsidian\ModernFrontend\Service\Dev\DevServerProcess;
use PHPUnit\Framework\TestCase;

class DevServerProcessTest extends TestCase
{
    public function testBuildDevServerArgsTargetsDevScriptForTheme(): void
    {
        $this->assertSame(
            ['pnpm', '--prefix', 'vite', 'dev', '--theme=MageObsidian/theme-base'],
            DevServerProcess::buildDevServerArgs('pnpm', 'MageObsidian/theme-base')
        );
    }

    public function testBuildBuildArgsTargetsBuildScriptForTheme(): void
    {
        $this->assertSame(
            ['pnpm', '--prefix', 'vite', 'build', '--theme=MageObsidian/theme-base'],
            DevServerProcess::buildBuildArgs('pnpm', 'MageObsidian/theme-base')
        );
    }

    public function testBuildSpawnCommandDetachesAndEchoesPid(): void
    {
        $cmd = DevServerProcess::buildSpawnCommand('pnpm', 'MageObsidian/theme-base', '/var/log/dev.log');

        $this->assertStringStartsWith('setsid ', $cmd);
        $this->assertStringContainsString('& echo $!', $cmd);
        $this->assertStringContainsString("> '/var/log/dev.log' 2>&1", $cmd);
        $this->assertStringContainsString("'--theme=MageObsidian/theme-base'", $cmd);
    }

    public function testBuildSpawnCommandEscapesHostileTheme(): void
    {
        $cmd = DevServerProcess::buildSpawnCommand('pnpm', 'a; rm -rf /', '/tmp/x.log');

        // The whole theme arg must stay inside one shell-escaped token, so the
        // embedded `;` cannot start a new command.
        $this->assertStringContainsString("'--theme=a; rm -rf /'", $cmd);
        $this->assertStringNotContainsString('; rm -rf / ', $cmd);
    }

    public function testStateRoundTrip(): void
    {
        $encoded = DevServerProcess::encodeState(4321, 'MageObsidian/theme-base');
        $decoded = DevServerProcess::decodeState($encoded);

        $this->assertSame(4321, $decoded['pid']);
        $this->assertSame('MageObsidian/theme-base', $decoded['theme']);
    }

    public function testDecodeStateRejectsGarbage(): void
    {
        $this->assertSame([], DevServerProcess::decodeState('not json'));
        $this->assertSame([], DevServerProcess::decodeState('{"theme":"x"}'));
        $this->assertSame([], DevServerProcess::decodeState('[]'));
    }

    public function testDecodeStateCoercesPidToInt(): void
    {
        $decoded = DevServerProcess::decodeState('{"pid":"99","theme":"t"}');
        $this->assertSame(99, $decoded['pid']);
    }
}
