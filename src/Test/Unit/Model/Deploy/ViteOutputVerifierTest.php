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
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverInterface;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Model\Deploy\DeployTargets;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputVerifier;
use PHPUnit\Framework\TestCase;

class ViteOutputVerifierTest extends TestCase
{
    private const ROOT = '/var/www/html';
    private const THEME = 'MageObsidian/default';
    private const SRC = '/var/www/html/vendor/mage-obsidian/theme-default';
    private const PARENT_THEME = 'MageObsidian/theme-base';
    private const PARENT_SRC = '/var/www/html/vendor/mage-obsidian/theme-base';

    public function testReportsNothingWhenEveryBuiltFileWasPublished(): void
    {
        $verifier = $this->verifier(
            built: ['lib/vue.js', 'MageObsidian_Storefront/js/nav.js'],
            published: ['lib/vue.js', 'MageObsidian_Storefront/js/nav.js']
        );

        $this->assertSame([], $verifier->findMissing($this->options(['en_US'])));
    }

    public function testReportsTheFilesThatNeverReachedPubStatic(): void
    {
        $verifier = $this->verifier(
            built: ['lib/vue.js', 'MageObsidian_Storefront/js/nav.js'],
            published: ['lib/vue.js']
        );

        $missing = $verifier->findMissing($this->options(['en_US']));

        $this->assertSame(
            ['MageObsidian/default@en_US' => ['MageObsidian_Storefront/js/nav.js']],
            $missing
        );
    }

    /**
     * The failure that started this: the deploy died early and the theme's whole
     * root — generated/ included — never got published.
     */
    public function testReportsAThemeWhoseOutputIsEntirelyAbsent(): void
    {
        $verifier = $this->verifier(built: ['lib/vue.js'], published: []);

        $this->assertSame(
            ['MageObsidian/default@en_US' => ['lib/vue.js']],
            $verifier->findMissing($this->options(['en_US']))
        );
    }

    // Every locale gets its own copy under pub/static, so one good locale says
    // nothing about the next.
    public function testChecksEveryLocaleSeparately(): void
    {
        $verifier = $this->verifier(
            built: ['lib/vue.js'],
            published: ['lib/vue.js'],
            publishedPerLocale: ['en_US' => ['lib/vue.js'], 'es_ES' => []]
        );

        $this->assertSame(
            ['MageObsidian/default@es_ES' => ['lib/vue.js']],
            $verifier->findMissing($this->options(['en_US', 'es_ES']))
        );
    }

    // A theme that was never built has nothing to publish; complaining about it
    // would fail deploys that legitimately skip the Vite build.
    public function testStaysQuietWhenTheThemeHasNoBuildOutput(): void
    {
        $verifier = $this->verifier(built: null, published: []);

        $this->assertSame([], $verifier->findMissing($this->options(['en_US'])));
    }

    /**
     * `readDirectoryRecursively` returns the directories along with the files.
     * Counting those as missing assets inflates the count and fills the failure
     * message with entries nobody can look up.
     */
    public function testIgnoresDirectoriesWhenComparing(): void
    {
        $verifier = $this->verifier(
            built: ['lib', 'lib/vue.js'],
            published: ['lib/vue.js'],
            directories: ['lib']
        );

        $this->assertSame([], $verifier->findMissing($this->options(['en_US'])));
    }

    /**
     * Magento never publishes dot-files, and Vite writes its own manifest to
     * `.vite/`. Demanding it would fail every single healthy deploy.
     */
    public function testIgnoresHiddenBuildArtifacts(): void
    {
        $verifier = $this->verifier(
            built: ['.vite/manifest.json', 'lib/vue.js'],
            published: ['lib/vue.js']
        );

        $this->assertSame([], $verifier->findMissing($this->options(['en_US'])));
    }

    /**
     * The regression this was written for. `--language` defaults to the
     * sentinel `all`; taking it for a locale looks under a
     * `pub/static/frontend/<theme>/all/` that Magento never writes, so a
     * flawless deploy came back with its entire output reported missing.
     */
    public function testResolvesTheAllSentinelInsteadOfLookingUpALocaleNamedAll(): void
    {
        $verifier = $this->verifier(
            built: ['lib/vue.js'],
            published: ['lib/vue.js'],
            targets: $this->realTargets(['en_US'])
        );

        $this->assertSame(
            [],
            $verifier->findMissing([DeployStaticOptions::LANGUAGE => ['all']])
        );
    }

    public function testResolvesAnAbsentLanguageOptionTheSameWay(): void
    {
        $verifier = $this->verifier(
            built: ['lib/vue.js'],
            published: ['lib/vue.js'],
            targets: $this->realTargets(['en_US'])
        );

        $this->assertSame([], $verifier->findMissing([]));
    }

    /**
     * Not knowing which locales were deployed means there is nothing to compare
     * against; reporting every file as missing would be worse than silence.
     */
    public function testStaysQuietWhenNoLocaleCouldBeResolved(): void
    {
        $verifier = $this->verifier(built: ['lib/vue.js'], published: [], targets: $this->realTargets([]));

        $this->assertSame([], $verifier->findMissing([]));
    }

    // Deploying one theme says nothing about the others, so demanding output
    // from a theme the run skipped invents a failure.
    public function testOnlyChecksTheThemesTheRunCovered(): void
    {
        $verifier = $this->verifier(
            built: ['lib/vue.js'],
            published: [],
            withParentTheme: true,
            targets: $this->realTargets(['en_US'])
        );

        $missing = $verifier->findMissing([
            DeployStaticOptions::LANGUAGE => ['en_US'],
            DeployStaticOptions::THEME => [self::THEME],
        ]);

        $this->assertSame(['MageObsidian/default@en_US' => ['lib/vue.js']], $missing);
    }

    public function testHandsTheDeployOptionsStraightToTheTargets(): void
    {
        $options = [DeployStaticOptions::LANGUAGE => ['all'], DeployStaticOptions::EXCLUDE_THEME => ['none']];

        $targets = $this->createMock(DeployTargets::class);
        $targets->expects($this->once())->method('locales')->with($options)->willReturn(['en_US']);
        $targets->expects($this->once())
            ->method('includesTheme')
            ->with(self::THEME, $options)
            ->willReturn(true);

        $verifier = $this->verifier(built: ['lib/vue.js'], published: ['lib/vue.js'], targets: $targets);

        $this->assertSame([], $verifier->findMissing($options));
    }

    /**
     * @param string[] $locales
     * @return array<string, mixed>
     */
    private function options(array $locales): array
    {
        return [DeployStaticOptions::LANGUAGE => $locales];
    }

    /**
     * A real DeployTargets over a stubbed locale resolver, so the sentinel
     * handling under test is the one that runs in production.
     *
     * @param string[] $usedLocales
     */
    private function realTargets(array $usedLocales): DeployTargets
    {
        $localeResolver = $this->createMock(LocaleResolver::class);
        $localeResolver->method('getUsedPackageLocales')->willReturn($usedLocales);

        $packageFactory = $this->createMock(PackageFactory::class);
        $packageFactory->method('create')->willReturn($this->createMock(Package::class));

        return new DeployTargets($localeResolver, $packageFactory);
    }

    /**
     * @param string[]|null $built null means the source directory does not exist
     * @param string[] $published
     * @param array<string, string[]>|null $publishedPerLocale
     * @param string[] $directories entries of $built that are directories
     * @param bool $withParentTheme register a second, entirely unpublished theme
     */
    private function verifier(
        ?array $built,
        array $published,
        ?array $publishedPerLocale = null,
        array $directories = [],
        ?DeployTargets $targets = null,
        bool $withParentTheme = false
    ): ViteOutputVerifier {
        $themes = [self::THEME => ['src' => self::SRC, 'parent' => null]];
        if ($withParentTheme) {
            $themes[self::PARENT_THEME] = ['src' => self::PARENT_SRC, 'parent' => null];
        }

        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('get')->willReturn(['themes' => $themes]);

        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getPath')->willReturn(self::ROOT . '/pub/static');

        $sourceDir = self::SRC . '/web/generated';
        $driver = $this->createMock(DriverInterface::class);

        $driver->method('isDirectory')->willReturnCallback(
            static function (string $path) use ($sourceDir, $built, $directories): bool {
                if ($path === $sourceDir) {
                    return $built !== null;
                }
                if ($path === self::PARENT_SRC . '/web/generated') {
                    return true;
                }
                foreach ($directories as $directory) {
                    if ($path === $sourceDir . '/' . $directory) {
                        return true;
                    }
                }
                return false;
            }
        );

        $driver->method('readDirectoryRecursively')->willReturnCallback(
            static fn (string $path): array => array_map(
                static fn (string $relative): string => $path . '/' . $relative,
                $built ?? []
            )
        );

        $driver->method('isExists')->willReturnCallback(
            static function (string $path) use ($published, $publishedPerLocale): bool {
                foreach ($publishedPerLocale ?? ['en_US' => $published] as $locale => $files) {
                    $base = self::ROOT . '/pub/static/frontend/' . self::THEME . '/' . $locale . '/generated/';
                    foreach ($files as $file) {
                        if ($path === $base . $file) {
                            return true;
                        }
                    }
                }
                return false;
            }
        );

        return new ViteOutputVerifier(
            $configManager,
            $directoryList,
            $driver,
            $targets ?? $this->realTargets(['en_US'])
        );
    }
}
