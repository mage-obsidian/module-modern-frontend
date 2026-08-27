<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Deploy;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DriverInterface;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputPublisher;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputTarget;
use PHPUnit\Framework\TestCase;

class ViteOutputPublisherTest extends TestCase
{
    private const SOURCE = '/var/www/html/vendor/mage-obsidian/theme-default/web/generated';
    private const TARGET = '/var/www/html/pub/static/frontend/MageObsidian/default/en_US/generated';

    public function testCopiesEachFileFromTheBuildToWhereItIsServed(): void
    {
        $copied = [];
        $driver = $this->driver($copied);

        $failed = (new ViteOutputPublisher($driver))->publish($this->target(['css/style.css', 'lib/vue.js']));

        $this->assertSame([], $failed);
        $this->assertSame(
            [
                self::SOURCE . '/css/style.css' => self::TARGET . '/css/style.css',
                self::SOURCE . '/lib/vue.js' => self::TARGET . '/lib/vue.js',
            ],
            $copied
        );
    }

    // A locale published for the first time has no directory tree yet.
    public function testCreatesTheDirectoryTheFileGoesInto(): void
    {
        $created = [];
        $driver = $this->driver($ignored, $created);

        (new ViteOutputPublisher($driver))->publish($this->target(['MageObsidian_Storefront/js/nav.js']));

        $this->assertSame([self::TARGET . '/MageObsidian_Storefront/js'], $created);
    }

    // One unwritable file must not stop the rest of the bundle from landing.
    public function testReportsTheFileItCouldNotCopyAndCarriesOn(): void
    {
        $driver = $this->driver($ignored, $alsoIgnored, refuse: 'lib/vue.js');

        $failed = (new ViteOutputPublisher($driver))->publish($this->target(['a.js', 'lib/vue.js', 'b.js']));

        $this->assertSame(['lib/vue.js'], $failed);
    }

    public function testTreatsAFilesystemErrorAsAFailedFileRatherThanAnAbortedDeploy(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getParentDirectory')->willReturn(self::TARGET);
        $driver->method('createDirectory')->willReturn(true);
        $driver->method('copy')->willThrowException(new FileSystemException(__('read-only')));

        $failed = (new ViteOutputPublisher($driver))->publish($this->target(['a.js']));

        $this->assertSame(['a.js'], $failed);
    }

    /**
     * @param string[] $files
     */
    private function target(array $files): ViteOutputTarget
    {
        return new ViteOutputTarget('MageObsidian/default', 'en_US', self::SOURCE, self::TARGET, $files);
    }

    /**
     * @param array<string, string>|null $copied
     * @param string[]|null $created
     */
    private function driver(&$copied = null, &$created = null, string $refuse = ''): DriverInterface
    {
        $copied = [];
        $created = [];

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getParentDirectory')->willReturnCallback(
            static fn (string $path): string => dirname($path)
        );
        $driver->method('createDirectory')->willReturnCallback(
            static function (string $path) use (&$created): bool {
                $created[] = $path;
                return true;
            }
        );
        $driver->method('copy')->willReturnCallback(
            static function (string $source, string $destination) use (&$copied, $refuse): bool {
                if ($refuse !== '' && str_ends_with($source, '/' . $refuse)) {
                    return false;
                }
                $copied[$source] = $destination;
                return true;
            }
        );

        return $driver;
    }
}
