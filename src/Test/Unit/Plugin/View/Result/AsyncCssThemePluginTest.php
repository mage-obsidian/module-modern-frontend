<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\View\Result;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Plugin\View\Result\AsyncCssThemePlugin;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Theme\Controller\Result\AsyncCssPlugin;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Mocks Magento framework types, so it runs in a Magento root (see phpunit.xml)
 * and is excluded from the standalone CI suite.
 */
class AsyncCssThemePluginTest extends TestCase
{
    public function testObsidianThemeSkipsNativeRewrite(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('MageObsidian/default')->willReturn(true);

        $plugin = $this->buildPlugin($this->designReturning('MageObsidian/default'), $configManager);
        $result = $this->createMock(Layout::class);
        $proceedCalled = false;

        $returned = $plugin->aroundAfterRenderResult(
            $this->createMock(AsyncCssPlugin::class),
            function () use (&$proceedCalled) {
                $proceedCalled = true;
                return $this->createMock(Layout::class);
            },
            $this->createMock(Layout::class),
            $result,
            $this->createMock(ResponseInterface::class)
        );

        $this->assertFalse($proceedCalled);
        $this->assertSame($result, $returned);
    }

    public function testLegacyThemeRunsNativeRewrite(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->with('Magento/luma')->willReturn(false);

        $plugin = $this->buildPlugin($this->designReturning('Magento/luma'), $configManager);
        $nativeResult = $this->createMock(Layout::class);

        $returned = $this->runProceed($plugin, $nativeResult);

        $this->assertSame($nativeResult, $returned);
    }

    public function testEmptyThemeCodeRunsNativeRewrite(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->never())->method('isThemeEnabled');

        $plugin = $this->buildPlugin($this->designReturning(''), $configManager);
        $nativeResult = $this->createMock(Layout::class);

        $this->assertSame($nativeResult, $this->runProceed($plugin, $nativeResult));
    }

    public function testConfigFailureDegradesToNativeRewriteAndLogs(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('isThemeEnabled')->willThrowException(new RuntimeException('contract missing'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $plugin = $this->buildPlugin($this->designReturning('MageObsidian/default'), $configManager, $logger);
        $nativeResult = $this->createMock(Layout::class);

        $this->assertSame($nativeResult, $this->runProceed($plugin, $nativeResult));
    }

    private function runProceed(AsyncCssThemePlugin $plugin, Layout $nativeResult): Layout
    {
        return $plugin->aroundAfterRenderResult(
            $this->createMock(AsyncCssPlugin::class),
            static fn (): Layout => $nativeResult,
            $this->createMock(Layout::class),
            $this->createMock(Layout::class),
            $this->createMock(ResponseInterface::class)
        );
    }

    private function buildPlugin(
        DesignInterface $design,
        ConfigManagerInterface $configManager,
        ?LoggerInterface $logger = null
    ): AsyncCssThemePlugin {
        return new AsyncCssThemePlugin(
            $design,
            $configManager,
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    private function designReturning(string $themeCode): DesignInterface
    {
        $theme = $this->createMock(ThemeInterface::class);
        $theme->method('getCode')->willReturn($themeCode);
        $design = $this->createMock(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($theme);

        return $design;
    }
}
