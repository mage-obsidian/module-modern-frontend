<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Deploy;

use Magento\Framework\Exception\LocalizedException;
use MageObsidian\ModernFrontend\Model\Deploy\ViteBuildPreflight;
use PHPUnit\Framework\TestCase;

class ViteBuildPreflightTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/obsidian-preflight-' . uniqid();
        mkdir($this->root . '/vite', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('chmod -R u+w ' . escapeshellarg($this->root) . ' && rm -rf ' . escapeshellarg($this->root));
    }

    public function testPassesWhenEveryTargetIsWritable(): void
    {
        mkdir($this->root . '/theme/web/generated', 0777, true);

        (new ViteBuildPreflight())->assertWritable($this->root . '/vite', [$this->root . '/theme/web/generated']);

        $this->addToAssertionCount(1);
    }

    public function testRejectsAReadOnlyViteHarness(): void
    {
        chmod($this->root . '/vite', 0555);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($this->root . '/vite');

        (new ViteBuildPreflight())->assertWritable($this->root . '/vite', []);
    }

    public function testRejectsAReadOnlyOutputDirThatViteWouldEmpty(): void
    {
        mkdir($this->root . '/theme/web/generated', 0777, true);
        chmod($this->root . '/theme/web/generated', 0555);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($this->root . '/theme/web/generated');

        (new ViteBuildPreflight())->assertWritable($this->root . '/vite', [$this->root . '/theme/web/generated']);
    }

    public function testChecksTheNearestExistingAncestorOfAMissingOutputDir(): void
    {
        mkdir($this->root . '/theme/web', 0777, true);
        chmod($this->root . '/theme/web', 0555);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($this->root . '/theme/web');

        (new ViteBuildPreflight())->assertWritable($this->root . '/vite', [$this->root . '/theme/web/generated']);
    }

    public function testNearestExistingWalksUpToAnExistingDirectory(): void
    {
        $this->assertSame($this->root, ViteBuildPreflight::nearestExisting($this->root . '/a/b/c'));
    }
}
