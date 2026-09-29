<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Deploy;

use Magento\Deploy\Console\DeployStaticOptions;
use Magento\Deploy\Package\LocaleResolver;
use Magento\Deploy\Package\Package;
use Magento\Deploy\Package\PackageFactory;
use MageObsidian\ModernFrontend\Model\Deploy\DeployTargets;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DeployTargetsTest extends TestCase
{
    /**
     * The bug this class exists for: `--language` defaults to the sentinel
     * `all`, and reading it literally sends the verifier looking for a
     * `pub/static/<area>/<theme>/all/` that Magento never writes.
     */
    public function testExpandsTheAllSentinelToTheLocalesInUse(): void
    {
        $targets = $this->targets(['en_US', 'es_ES']);

        $this->assertSame(
            ['en_US', 'es_ES'],
            $targets->locales([DeployStaticOptions::LANGUAGE => ['all']])
        );
    }

    public function testExpandsAnAbsentLanguageOptionTheSameWay(): void
    {
        $targets = $this->targets(['en_US']);

        $this->assertSame(['en_US'], $targets->locales([]));
    }

    public function testKeepsExplicitLocalesAsGiven(): void
    {
        $targets = $this->targets(['en_US', 'es_ES']);

        $this->assertSame(
            ['de_DE'],
            $targets->locales([DeployStaticOptions::LANGUAGE => ['de_DE']])
        );
    }

    public function testDropsExcludedLocales(): void
    {
        $targets = $this->targets(['en_US', 'es_ES']);

        $this->assertSame(
            ['en_US'],
            $targets->locales([
                DeployStaticOptions::LANGUAGE => ['all'],
                DeployStaticOptions::EXCLUDE_LANGUAGE => ['es_ES'],
            ])
        );
    }

    /**
     * Magento's own rule (`PackagePool::isIncluded()`): once anything is
     * excluded the inclusion list stops being consulted. Diverging from it
     * would flag packages the deploy never set out to write.
     */
    public function testAnExclusionListMakesTheInclusionListIrrelevant(): void
    {
        $targets = $this->targets(['en_US', 'es_ES']);

        $this->assertSame(
            [],
            $targets->locales([
                DeployStaticOptions::LANGUAGE => ['en_US'],
                DeployStaticOptions::EXCLUDE_LANGUAGE => ['en_US'],
            ])
        );
    }

    public function testTreatsTheNoneSentinelAsNoExclusionAtAll(): void
    {
        $targets = $this->targets(['en_US', 'es_ES']);

        $this->assertSame(
            ['en_US', 'es_ES'],
            $targets->locales([
                DeployStaticOptions::LANGUAGE => ['all'],
                DeployStaticOptions::EXCLUDE_LANGUAGE => ['none'],
            ])
        );
    }

    /**
     * A verifier that cannot work out what was deployed has to stay quiet:
     * failing a deploy over the verification itself is worse than not checking.
     */
    public function testYieldsNoLocalesWhenTheyCannotBeResolved(): void
    {
        $localeResolver = $this->createMock(LocaleResolver::class);
        $localeResolver->method('getUsedPackageLocales')
            ->willThrowException(new RuntimeException('no database'));

        $targets = new DeployTargets($localeResolver, $this->packageFactory());

        $this->assertSame([], $targets->locales([DeployStaticOptions::LANGUAGE => ['all']]));
    }

    public function testResolvesTheLocalesOnlyOnce(): void
    {
        $localeResolver = $this->createMock(LocaleResolver::class);
        $localeResolver->expects($this->once())
            ->method('getUsedPackageLocales')
            ->willReturn(['en_US']);

        $targets = new DeployTargets($localeResolver, $this->packageFactory());

        $targets->locales([]);
        $targets->locales([]);
    }

    public function testIncludesEveryThemeWhenNoneWasSingledOut(): void
    {
        $targets = $this->targets(['en_US']);

        $this->assertTrue($targets->includesTheme('MageObsidian/theme-base', [
            DeployStaticOptions::THEME => ['all'],
            DeployStaticOptions::EXCLUDE_THEME => ['none'],
        ]));
    }

    public function testLeavesOutThemesTheRunDidNotCover(): void
    {
        $targets = $this->targets(['en_US']);
        $options = [DeployStaticOptions::THEME => ['MageObsidian/default']];

        $this->assertTrue($targets->includesTheme('MageObsidian/default', $options));
        $this->assertFalse($targets->includesTheme('MageObsidian/theme-base', $options));
    }

    public function testDropsExcludedThemes(): void
    {
        $targets = $this->targets(['en_US']);
        $options = [DeployStaticOptions::EXCLUDE_THEME => ['MageObsidian/theme-base']];

        $this->assertFalse($targets->includesTheme('MageObsidian/theme-base', $options));
        $this->assertTrue($targets->includesTheme('MageObsidian/default', $options));
    }

    // The Vite build plugin already accepts the underscore spelling of a theme,
    // so the two halves of the deploy must agree on what a theme is called.
    public function testMatchesThemesSpeltWithAnUnderscore(): void
    {
        $targets = $this->targets(['en_US']);

        $this->assertTrue($targets->includesTheme('MageObsidian/default', [
            DeployStaticOptions::THEME => ['MageObsidian_default'],
        ]));
    }

    /**
     * @param string[] $usedLocales
     */
    private function targets(array $usedLocales): DeployTargets
    {
        $localeResolver = $this->createMock(LocaleResolver::class);
        $localeResolver->method('getUsedPackageLocales')->willReturn($usedLocales);

        return new DeployTargets($localeResolver, $this->packageFactory());
    }

    private function packageFactory(): PackageFactory
    {
        $factory = $this->createMock(PackageFactory::class);
        $factory->method('create')->willReturn($this->createMock(Package::class));

        return $factory;
    }
}
