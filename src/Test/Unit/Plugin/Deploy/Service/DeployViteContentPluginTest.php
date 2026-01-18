<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Deploy\Service;

use MageObsidian\ModernFrontend\Plugin\Deploy\Service\DeployViteContentPlugin;
use PHPUnit\Framework\TestCase;

class DeployViteContentPluginTest extends TestCase
{
    public function testBuildCommandArgsWithoutThemeBuildsEveryTheme(): void
    {
        $this->assertSame(
            ['pnpm', '--prefix', 'vite', 'build'],
            DeployViteContentPlugin::buildCommandArgs('pnpm', null)
        );
    }

    public function testBuildCommandArgsTranslatesThemeNameToPath(): void
    {
        $this->assertSame(
            ['pnpm', '--prefix', 'vite', 'build', '--theme=Vendor/theme-base'],
            DeployViteContentPlugin::buildCommandArgs('pnpm', 'Vendor_theme-base')
        );
    }

    public function testBuildCommandArgsKeepsAbsoluteBinaryPath(): void
    {
        $this->assertSame(
            ['/usr/bin/pnpm', '--prefix', 'vite', 'build', '--theme=Acme/Shop'],
            DeployViteContentPlugin::buildCommandArgs('/usr/bin/pnpm', 'Acme_Shop')
        );
    }

    /**
     * A theme name carrying shell metacharacters must remain a single argv
     * element; the array form guarantees no shell ever expands it.
     */
    public function testBuildCommandArgsDoesNotSplitHostileThemeName(): void
    {
        $args = DeployViteContentPlugin::buildCommandArgs('pnpm', 'Evil_x; rm -rf /');

        $this->assertCount(5, $args);
        $this->assertSame('--theme=Evil/x; rm -rf /', end($args));
    }

    public function testResolveTimeoutFallsBackToFiniteDefault(): void
    {
        $this->assertSame(
            DeployViteContentPlugin::DEFAULT_BUILD_TIMEOUT,
            DeployViteContentPlugin::resolveTimeout(false)
        );
        $this->assertSame(
            DeployViteContentPlugin::DEFAULT_BUILD_TIMEOUT,
            DeployViteContentPlugin::resolveTimeout('')
        );
        $this->assertSame(
            DeployViteContentPlugin::DEFAULT_BUILD_TIMEOUT,
            DeployViteContentPlugin::resolveTimeout('not-a-number')
        );
    }

    public function testResolveTimeoutAcceptsPositiveOverride(): void
    {
        $this->assertSame(600.0, DeployViteContentPlugin::resolveTimeout('600'));
        $this->assertSame(42.5, DeployViteContentPlugin::resolveTimeout('42.5'));
    }

    public function testResolveTimeoutZeroOrNegativeDisablesTheLimit(): void
    {
        $this->assertNull(DeployViteContentPlugin::resolveTimeout('0'));
        $this->assertNull(DeployViteContentPlugin::resolveTimeout('-5'));
    }
}
