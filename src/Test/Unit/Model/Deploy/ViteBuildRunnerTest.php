<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Deploy;

use MageObsidian\ModernFrontend\Model\Deploy\ViteBuildRunner;
use PHPUnit\Framework\TestCase;

class ViteBuildRunnerTest extends TestCase
{
    public function testBuildCommandArgsWithoutThemeBuildsEveryTheme(): void
    {
        $this->assertSame(
            ['pnpm', '--prefix', 'vite', 'build'],
            ViteBuildRunner::buildCommandArgs('pnpm', null)
        );
    }

    public function testBuildCommandArgsTranslatesThemeNameToPath(): void
    {
        $this->assertSame(
            ['pnpm', '--prefix', 'vite', 'build', '--theme=Vendor/theme-base'],
            ViteBuildRunner::buildCommandArgs('pnpm', 'Vendor_theme-base')
        );
    }

    public function testBuildCommandArgsKeepsAbsoluteBinaryPath(): void
    {
        $this->assertSame(
            ['/usr/bin/pnpm', '--prefix', 'vite', 'build', '--theme=Acme/Shop'],
            ViteBuildRunner::buildCommandArgs('/usr/bin/pnpm', 'Acme_Shop')
        );
    }

    public function testBuildCommandArgsDoesNotSplitHostileThemeName(): void
    {
        $args = ViteBuildRunner::buildCommandArgs('pnpm', 'Evil_x; rm -rf /');

        $this->assertCount(5, $args);
        $this->assertSame('--theme=Evil/x; rm -rf /', end($args));
    }

    public function testResolveTimeoutFallsBackToFiniteDefault(): void
    {
        $this->assertSame(
            ViteBuildRunner::DEFAULT_BUILD_TIMEOUT,
            ViteBuildRunner::resolveTimeout(false)
        );
        $this->assertSame(
            ViteBuildRunner::DEFAULT_BUILD_TIMEOUT,
            ViteBuildRunner::resolveTimeout('')
        );
        $this->assertSame(
            ViteBuildRunner::DEFAULT_BUILD_TIMEOUT,
            ViteBuildRunner::resolveTimeout('not-a-number')
        );
    }

    public function testResolveTimeoutAcceptsPositiveOverride(): void
    {
        $this->assertSame(600.0, ViteBuildRunner::resolveTimeout('600'));
        $this->assertSame(42.5, ViteBuildRunner::resolveTimeout('42.5'));
    }

    public function testResolveTimeoutZeroOrNegativeDisablesTheLimit(): void
    {
        $this->assertNull(ViteBuildRunner::resolveTimeout('0'));
        $this->assertNull(ViteBuildRunner::resolveTimeout('-5'));
    }
}
