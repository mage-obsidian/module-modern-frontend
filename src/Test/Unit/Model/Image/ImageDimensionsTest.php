<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Image;

use MageObsidian\ModernFrontend\Model\Image\ImageDimensions;
use PHPUnit\Framework\TestCase;

class ImageDimensionsTest extends TestCase
{
    private ImageDimensions $dimensions;
    private string $tmpFile = '';

    protected function setUp(): void
    {
        $this->dimensions = new ImageDimensions();
    }

    protected function tearDown(): void
    {
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testReadsIntrinsicDimensionsOfRealImage(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required to generate the fixture image.');
        }

        // 3x2 so a width/height transposition bug would be caught.
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'mageobsidian_img_') . '.png';
        $gd = imagecreatetruecolor(3, 2);
        imagepng($gd, $this->tmpFile);
        imagedestroy($gd);

        $this->assertSame([3, 2], $this->dimensions->read($this->tmpFile));
    }

    public function testReturnsNullForMissingFile(): void
    {
        $this->assertNull($this->dimensions->read('/no/such/file.png'));
    }

    public function testReturnsNullForEmptyPath(): void
    {
        $this->assertNull($this->dimensions->read(''));
    }

    public function testReturnsNullForNonImageFile(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'mageobsidian_txt_') . '.txt';
        file_put_contents($this->tmpFile, 'not an image');

        $this->assertNull($this->dimensions->read($this->tmpFile));
    }
}
