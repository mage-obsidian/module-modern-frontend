<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Deploy;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverInterface;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputVerifier;
use PHPUnit\Framework\TestCase;

class ViteOutputVerifierTest extends TestCase
{
    private const ROOT = '/var/www/html';
    private const THEME = 'MageObsidian/default';
    private const SRC = '/var/www/html/vendor/mage-obsidian/theme-default';

    public function testReportsNothingWhenEveryBuiltFileWasPublished(): void
    {
        $verifier = $this->verifier(
            built: ['lib/vue.js', 'MageObsidian_Storefront/js/nav.js'],
            published: ['lib/vue.js', 'MageObsidian_Storefront/js/nav.js']
        );

        $this->assertSame([], $verifier->findMissing(['en_US']));
    }

    public function testReportsTheFilesThatNeverReachedPubStatic(): void
    {
        $verifier = $this->verifier(
            built: ['lib/vue.js', 'MageObsidian_Storefront/js/nav.js'],
            published: ['lib/vue.js']
        );

        $missing = $verifier->findMissing(['en_US']);

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
            $verifier->findMissing(['en_US'])
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
            $verifier->findMissing(['en_US', 'es_ES'])
        );
    }

    // A theme that was never built has nothing to publish; complaining about it
    // would fail deploys that legitimately skip the Vite build.
    public function testStaysQuietWhenTheThemeHasNoBuildOutput(): void
    {
        $verifier = $this->verifier(built: null, published: []);

        $this->assertSame([], $verifier->findMissing(['en_US']));
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

        $this->assertSame([], $verifier->findMissing(['en_US']));
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

        $this->assertSame([], $verifier->findMissing(['en_US']));
    }

    /**
     * @param string[]|null $built null means the source directory does not exist
     * @param string[] $published
     * @param array<string, string[]>|null $publishedPerLocale
     * @param string[] $directories entries of $built that are directories
     */
    private function verifier(
        ?array $built,
        array $published,
        ?array $publishedPerLocale = null,
        array $directories = []
    ): ViteOutputVerifier {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('get')->willReturn([
            'themes' => [self::THEME => ['src' => self::SRC, 'parent' => null]],
        ]);

        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getPath')->willReturn(self::ROOT . '/pub/static');

        $sourceDir = self::SRC . '/web/generated';
        $driver = $this->createMock(DriverInterface::class);

        $driver->method('isDirectory')->willReturnCallback(
            static function (string $path) use ($sourceDir, $built, $directories): bool {
                if ($path === $sourceDir) {
                    return $built !== null;
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
                static fn (string $relative): string => $sourceDir . '/' . $relative,
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

        return new ViteOutputVerifier($configManager, $directoryList, $driver);
    }
}
