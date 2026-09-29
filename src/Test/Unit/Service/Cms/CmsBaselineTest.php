<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Cms;

use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Asset\File as AssetFile;
use Magento\Framework\View\Asset\File\NotFoundException;
use Magento\Framework\View\Asset\Repository;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Service\Cms\CmsBaseline;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CmsBaselineTest extends TestCase
{
    public function testABaselineThatWasNeverWrittenIsAbsent(): void
    {
        $this->assertSame(CmsBaseline::ABSENT, $this->baseline(null)->status());
    }

    public function testABaselineWithNoClassesIsEmpty(): void
    {
        $this->assertSame(CmsBaseline::EMPTY, $this->baseline('[]')->status());
    }

    public function testABaselineWithClassesIsPresent(): void
    {
        $this->assertSame(CmsBaseline::PRESENT, $this->baseline('["text-sale"]')->status());
    }

    private function baseline(?string $contents): CmsBaseline
    {
        $asset = $this->createStub(AssetFile::class);
        if ($contents === null) {
            $asset->method('getSourceFile')->willThrowException(new NotFoundException('no baseline'));
        } else {
            $asset->method('getSourceFile')->willReturn('/t/web/generated/cms-candidates.json');
        }
        $repository = $this->createStub(Repository::class);
        $repository->method('createAsset')->willReturn($asset);

        $state = $this->createStub(State::class);
        $state->method('emulateAreaCode')->willReturnCallback(fn (string $area, callable $callback) => $callback());

        $driver = $this->createStub(File::class);
        $driver->method('isExists')->willReturn($contents !== null);
        $driver->method('fileGetContents')->willReturn((string)$contents);

        $config = $this->createStub(ConfigProvider::class);
        $config->method('getViteGeneratedPath')->willReturn('generated');

        return new CmsBaseline($repository, $config, $state, $driver, $this->createStub(LoggerInterface::class));
    }
}
