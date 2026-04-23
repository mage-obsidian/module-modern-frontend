<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Repository;
use MageObsidian\ModernFrontend\Model\Image\ImageDimensions;
use MageObsidian\ModernFrontend\Model\Image\ImageRenderer;
use MageObsidian\ModernFrontend\ViewModel\Image;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Mocks the Magento asset Repository/Request, so it runs in the full suite
 * (phpunit.xml), excluded from the standalone CI suite. Uses the real (pure)
 * ImageRenderer and a stubbed ImageDimensions.
 */
class ImageTest extends TestCase
{
    private Repository&MockObject $assetRepository;
    private RequestInterface&MockObject $request;
    private ImageDimensions&MockObject $dimensions;
    private Image $viewModel;

    protected function setUp(): void
    {
        $this->assetRepository = $this->createMock(Repository::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->dimensions = $this->createMock(ImageDimensions::class);
        $this->request->method('isSecure')->willReturn(true);

        $this->viewModel = new Image(
            $this->assetRepository,
            $this->request,
            new ImageRenderer(),
            $this->dimensions
        );
    }

    public function testPlainUrlIsRenderedWithoutAssetResolution(): void
    {
        $this->assetRepository->expects($this->never())->method('getUrlWithParams');

        $html = $this->viewModel->render('https://acme.test/a.jpg', ['width' => 100, 'height' => 80]);

        $this->assertStringContainsString('src="https://acme.test/a.jpg"', $html);
        $this->assertStringContainsString('width="100"', $html);
    }

    public function testAssetIdResolvesUrlAndAutoDetectsDimensions(): void
    {
        $this->assetRepository->method('getUrlWithParams')
            ->with('Acme_Catalog::images/x.png', ['_secure' => true])
            ->willReturn('https://acme.test/static/x.png');

        $asset = $this->createMock(File::class);
        $asset->method('getSourceFile')->willReturn('/abs/x.png');
        $this->assetRepository->method('createAsset')->willReturn($asset);
        $this->dimensions->method('read')->with('/abs/x.png')->willReturn([640, 480]);

        $html = $this->viewModel->render('Acme_Catalog::images/x.png');

        $this->assertStringContainsString('src="https://acme.test/static/x.png"', $html);
        $this->assertStringContainsString('width="640"', $html);
        $this->assertStringContainsString('height="480"', $html);
    }

    public function testExplicitDimensionsSkipAutoDetection(): void
    {
        $this->assetRepository->method('getUrlWithParams')->willReturn('https://acme.test/static/x.png');
        $this->assetRepository->expects($this->never())->method('createAsset');
        $this->dimensions->expects($this->never())->method('read');

        $html = $this->viewModel->render('Acme_Catalog::images/x.png', ['width' => 50, 'height' => 50]);

        $this->assertStringContainsString('width="50"', $html);
    }

    public function testUndetectableAssetStillRendersWithoutDimensions(): void
    {
        $this->assetRepository->method('getUrlWithParams')->willReturn('https://acme.test/static/x.png');
        $asset = $this->createMock(File::class);
        $asset->method('getSourceFile')->willReturn('/abs/x.png');
        $this->assetRepository->method('createAsset')->willReturn($asset);
        $this->dimensions->method('read')->willReturn(null);

        $html = $this->viewModel->render('Acme_Catalog::images/x.png');

        $this->assertStringContainsString('src="https://acme.test/static/x.png"', $html);
        $this->assertStringNotContainsString('width=', $html);
    }
}
