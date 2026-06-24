<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Dev;

use MageObsidian\ModernFrontend\Service\Dev\ThemeSelector;
use PHPUnit\Framework\TestCase;

class ThemeSelectorTest extends TestCase
{
    private const THEMES = ['MageObsidian/theme-base', 'MageObsidian/default'];

    public function testExplicitThemeWinsWithoutPrompting(): void
    {
        $prompted = false;
        $result = ThemeSelector::resolve('Acme/aurora', self::THEMES, true, function () use (&$prompted) {
            $prompted = true;
            return null;
        });

        $this->assertSame('Acme/aurora', $result);
        $this->assertFalse($prompted, 'An explicit theme must not trigger the picker.');
    }

    public function testInteractivePickerReturnsChosenTheme(): void
    {
        $result = ThemeSelector::resolve('', self::THEMES, true, function (array $themes) {
            $this->assertSame(self::THEMES, $themes);
            return $themes[1];
        });

        $this->assertSame('MageObsidian/default', $result);
    }

    public function testReturnsNullWhenNonInteractive(): void
    {
        $prompted = false;
        $result = ThemeSelector::resolve('', self::THEMES, false, function () use (&$prompted) {
            $prompted = true;
            return null;
        });

        $this->assertNull($result);
        $this->assertFalse($prompted);
    }

    public function testReturnsNullWhenNoThemesAvailable(): void
    {
        $result = ThemeSelector::resolve('', [], true, fn () => 'whatever');

        $this->assertNull($result);
    }

    public function testReturnsNullWhenPickerCancels(): void
    {
        $result = ThemeSelector::resolve('', self::THEMES, true, fn () => null);

        $this->assertNull($result);
    }
}
