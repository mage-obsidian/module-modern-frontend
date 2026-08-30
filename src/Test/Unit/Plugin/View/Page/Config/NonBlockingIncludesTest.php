<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\View\Page\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Framework\View\Page\Config;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Plugin\View\Page\Config\NonBlockingIncludes;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use PHPUnit\Framework\TestCase;

class NonBlockingIncludesTest extends TestCase
{
    private const string SWAP_MARKER = '<script data-swap>SWAP</script>';

    public function testBothFlagsOffLeaveTheBlobByteForByteIdentical(): void
    {
        $includes = '<meta name="probe" content="keepme"/>' . "\n"
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>' . "\n"
            . '<!-- <script src="https://cdn.test/commented.js"></script> -->' . "\n"
            . '<script src="https://cdn.test/a.js"></script>' . "\n"
            . '<script>window.aReady = true;</script>' . "\n"
            . '<script src="https://cdn.test/b.js"></script>' . "\n"
            . '<script async src="https://cdn.test/async.js"></script>' . "\n"
            . '<script type="module" src="https://cdn.test/m.mjs"></script>' . "\n"
            . '<link rel="stylesheet" href="https://cdn.test/one.css"/>' . "\n"
            . '<link rel="stylesheet" media="screen" href="https://cdn.test/two.css"'
            . ' integrity="sha384-abc123" crossorigin/>' . "\n"
            . 'plain text 5 < 6 & 7 > 4';

        $output = $this->rewrite($includes, false, false);

        $this->assertSame($includes, $output);
        $this->assertStringNotContainsString('defer', $output);
        $this->assertStringNotContainsString('preload', $output);
        $this->assertStringNotContainsString(self::SWAP_MARKER, $output);
    }

    public function testExternalScriptGetsDefer(): void
    {
        $includes = '<script src="https://cdn.test/lib.js"></script>';

        $this->assertSame(
            '<script src="https://cdn.test/lib.js" defer></script>',
            $this->rewrite($includes, true)
        );
    }

    public function testAsyncScriptIsUntouched(): void
    {
        $includes = '<script async src="https://cdn.test/lib.js"></script>';

        $this->assertSame($includes, $this->rewrite($includes, true));
    }

    public function testAlreadyDeferredScriptIsUntouched(): void
    {
        $includes = '<script defer src="https://cdn.test/lib.js"></script>';

        $this->assertSame($includes, $this->rewrite($includes, true));
    }

    public function testModuleScriptIsUntouched(): void
    {
        $includes = '<script type="module" src="https://cdn.test/lib.mjs"></script>';

        $this->assertSame($includes, $this->rewrite($includes, true));
    }

    public function testInlineScriptIsUntouched(): void
    {
        $includes = '<script>window.dataLayer = window.dataLayer || [];</script>';

        $this->assertSame($includes, $this->rewrite($includes, true));
    }

    public function testBlockingAttributeKeepsScriptUntouched(): void
    {
        $includes = '<script src="https://cdn.test/lib.js" data-obsidian-blocking></script>';

        $this->assertSame($includes, $this->rewrite($includes, true));
    }

    public function testBlockingAttributeKeepsStylesheetUntouched(): void
    {
        $includes = '<link rel="stylesheet" href="https://cdn.test/a.css" data-obsidian-blocking/>';

        $this->assertSame($includes, $this->rewrite($includes, true, true));
    }

    public function testStylesheetIsUntouchedWhileTheStyleFlagIsOff(): void
    {
        $includes = '<link rel="stylesheet" href="https://cdn.test/a.css"/>';

        $this->assertSame($includes, $this->rewrite($includes, true));
    }

    public function testStylesheetBecomesAPreloadWithNoscriptFallback(): void
    {
        $output = $this->rewrite('<link rel="stylesheet" href="https://cdn.test/a.css"/>', true, true);

        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="https://cdn.test/a.css" data-obsidian-include-sheet/>',
            $output
        );
        $this->assertStringContainsString(
            '<noscript><link rel="stylesheet" href="https://cdn.test/a.css"/></noscript>',
            $output
        );
        $this->assertStringContainsString(self::SWAP_MARKER, $output);
    }

    public function testTwoStylesheetsEmitASingleSwapScript(): void
    {
        $includes = '<link rel="stylesheet" href="https://cdn.test/a.css"/>'
            . '<link rel="stylesheet" href="https://cdn.test/b.css"/>';

        $output = $this->rewrite($includes, true, true);

        $this->assertSame(2, substr_count($output, 'data-obsidian-include-sheet'));
        $this->assertSame(1, substr_count($output, self::SWAP_MARKER));
    }

    public function testUnrelatedMarkupSurvivesByteForByte(): void
    {
        $includes = '<meta name="facebook-domain-verification" content="abc123"/>' . "\n"
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>' . "\n"
            . '<!-- <script src="https://cdn.test/commented.js"></script> -->' . "\n"
            . 'plain text 5 < 6 & 7 > 4' . "\n"
            . '<script>console.log("<script src=nested.js>");</script>';

        $this->assertSame($includes, $this->rewrite($includes, true, true));
    }

    public function testEmptyIncludesStayEmptyAndEmitNoSwapScript(): void
    {
        $this->assertSame('', $this->rewrite('', true));
        $this->assertSame("  \n\t", $this->rewrite("  \n\t", true, true));
    }

    public function testNonObsidianThemeKeepsTheBlobIntact(): void
    {
        $includes = '<script src="https://cdn.test/lib.js"></script>'
            . '<link rel="stylesheet" href="https://cdn.test/a.css"/>';

        $this->assertSame($includes, $this->rewrite($includes, true, true, false));
    }

    public function testSingleQuotedAndUnquotedAttributesAreHandled(): void
    {
        $output = $this->rewrite(
            "<script src='https://cdn.test/single.js'></script>"
            . '<script src=https://cdn.test/bare.js></script>'
            . "<link rel='stylesheet' href='https://cdn.test/single.css'/>"
            . '<link rel=stylesheet href=https://cdn.test/bare.css>',
            true,
            true
        );

        $this->assertStringContainsString("<script src='https://cdn.test/single.js' defer></script>", $output);
        $this->assertStringContainsString('<script src=https://cdn.test/bare.js defer></script>', $output);
        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="https://cdn.test/single.css" data-obsidian-include-sheet/>',
            $output
        );
        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="https://cdn.test/bare.css" data-obsidian-include-sheet/>',
            $output
        );
    }

    public function testUppercaseTagNamesAndMultilineAttributesAreHandled(): void
    {
        $includes = "<SCRIPT\n    SRC=\"https://cdn.test/lib.js\"\n    id=\"tag\"\n></SCRIPT>";

        $this->assertSame(
            "<SCRIPT\n    SRC=\"https://cdn.test/lib.js\"\n    id=\"tag\" defer></SCRIPT>",
            $this->rewrite($includes, true)
        );
    }

    public function testSelfClosingScriptKeepsItsSlash(): void
    {
        $this->assertSame(
            '<script src="https://cdn.test/lib.js" defer/>',
            $this->rewrite('<script src="https://cdn.test/lib.js" />', true)
        );
    }

    public function testMalformedMarkupDoesNotThrow(): void
    {
        $includes = '<script src="https://cdn.test/unterminated.js'
            . '<link rel="stylesheet" href='
            . '< script src="x.js">'
            . '<scriptish src="y.js"></scriptish>'
            . '<!-- unterminated comment';

        $this->assertSame($includes, $this->rewrite($includes, true, true));
    }

    public function testExternalFollowedByInlineIsLeftBlocking(): void
    {
        $includes = '<script src="https://cdn.test/lib.js"></script>'
            . '<script>lib.init();</script>';

        $this->assertSame($includes, $this->rewrite($includes, true));
    }

    public function testExternalWithNoInlineAfterItIsDeferred(): void
    {
        $includes = '<script>lib.boot();</script>'
            . '<script src="https://cdn.test/lib.js"></script>';

        $this->assertSame(
            '<script>lib.boot();</script><script src="https://cdn.test/lib.js" defer></script>',
            $this->rewrite($includes, true)
        );
    }

    public function testDeferIsDecidedPerPosition(): void
    {
        $includes = '<script src="a.js"></script>'
            . '<script>a.init()</script>'
            . '<script src="b.js"></script>';

        $this->assertSame(
            '<script src="a.js"></script><script>a.init()</script><script src="b.js" defer></script>',
            $this->rewrite($includes, true)
        );
    }

    public function testEveryExternalBeforeASingleInlineStaysBlocking(): void
    {
        $includes = '<script src="a.js"></script>'
            . '<script src="b.js"></script>'
            . '<script src="c.js"></script>'
            . '<script>boot()</script>';

        $this->assertSame($includes, $this->rewrite($includes, true));
    }

    public function testHrefWithADoubleQuoteIsEscapedIntoThePreload(): void
    {
        $output = $this->rewrite("<link rel='stylesheet' href='a\".css'/>", true, true);

        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="a&quot;.css" data-obsidian-include-sheet/>',
            $output
        );
    }

    public function testPrintOnlySheetIsLeftIntact(): void
    {
        $includes = '<link rel="stylesheet" media="print" href="https://cdn.test/print.css"/>';

        $output = $this->rewrite($includes, true, true);

        $this->assertSame($includes, $output);
        $this->assertStringNotContainsString('preload', $output);
        $this->assertStringNotContainsString('<noscript>', $output);
    }

    public function testPrintOnlySheetIsDetectedRegardlessOfCaseAndPadding(): void
    {
        $includes = '<link rel="stylesheet" media="  PRINT " href="https://cdn.test/print.css"/>';

        $this->assertSame($includes, $this->rewrite($includes, true, true));
    }

    public function testScreenMediaIsConvertedAndSurvivesInBothTags(): void
    {
        $output = $this->rewrite(
            '<link rel="stylesheet" media="screen" href="https://cdn.test/a.css"/>',
            true,
            true
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="https://cdn.test/a.css" media="screen"'
            . ' data-obsidian-include-sheet/>',
            $output
        );
        $this->assertStringContainsString(
            '<noscript><link rel="stylesheet" href="https://cdn.test/a.css" media="screen"/></noscript>',
            $output
        );
    }

    public function testMediaQueryIsConvertedAndSurvivesInBothTags(): void
    {
        $output = $this->rewrite(
            '<link rel="stylesheet" media="print and (min-width: 30em)" href="https://cdn.test/a.css"/>',
            true,
            true
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="https://cdn.test/a.css"'
            . ' media="print and (min-width: 30em)" data-obsidian-include-sheet/>',
            $output
        );
        $this->assertStringContainsString(
            '<noscript><link rel="stylesheet" href="https://cdn.test/a.css"'
            . ' media="print and (min-width: 30em)"/></noscript>',
            $output
        );
    }

    public function testIntegrityAndCrossoriginSurviveInBothTags(): void
    {
        $output = $this->rewrite(
            '<link rel="stylesheet" href="https://cdn.test/a.css"'
            . ' integrity="sha384-abc123" crossorigin="anonymous"/>',
            true,
            true
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="https://cdn.test/a.css"'
            . ' integrity="sha384-abc123" crossorigin="anonymous" data-obsidian-include-sheet/>',
            $output
        );
        $this->assertStringContainsString(
            '<noscript><link rel="stylesheet" href="https://cdn.test/a.css"'
            . ' integrity="sha384-abc123" crossorigin="anonymous"/></noscript>',
            $output
        );
    }

    public function testValuelessCrossoriginStaysValueless(): void
    {
        $output = $this->rewrite(
            '<link rel="stylesheet" href="https://cdn.test/a.css" integrity="sha384-abc123" crossorigin/>',
            true,
            true
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="https://cdn.test/a.css"'
            . ' integrity="sha384-abc123" crossorigin data-obsidian-include-sheet/>',
            $output
        );
        $this->assertStringNotContainsString('crossorigin=""', $output);
    }

    public function testIdTitleAndReferrerpolicySurviveInBothTags(): void
    {
        $output = $this->rewrite(
            '<link rel="stylesheet" href="https://cdn.test/a.css" id="vendor-css"'
            . ' title="Vendor" referrerpolicy="no-referrer"/>',
            true,
            true
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="https://cdn.test/a.css" id="vendor-css" title="Vendor"'
            . ' referrerpolicy="no-referrer" data-obsidian-include-sheet/>',
            $output
        );
        $this->assertStringContainsString(
            '<noscript><link rel="stylesheet" href="https://cdn.test/a.css" id="vendor-css" title="Vendor"'
            . ' referrerpolicy="no-referrer"/></noscript>',
            $output
        );
    }

    public function testEveryPreservedAttributeSurvivesTogetherInSourceOrder(): void
    {
        $output = $this->rewrite(
            '<link rel="stylesheet" media="screen" integrity="sha384-abc123" crossorigin="anonymous"'
            . ' referrerpolicy="no-referrer" id="vendor-css" title="Vendor" href="https://cdn.test/a.css"'
            . ' data-extra="dropped"/>',
            true,
            true
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="style" href="https://cdn.test/a.css" media="screen"'
            . ' integrity="sha384-abc123" crossorigin="anonymous" referrerpolicy="no-referrer"'
            . ' id="vendor-css" title="Vendor" data-obsidian-include-sheet/>',
            $output
        );
        $this->assertStringContainsString(
            '<noscript><link rel="stylesheet" href="https://cdn.test/a.css" media="screen"'
            . ' integrity="sha384-abc123" crossorigin="anonymous" referrerpolicy="no-referrer"'
            . ' id="vendor-css" title="Vendor"/></noscript>',
            $output
        );
        $this->assertStringNotContainsString('data-extra', $output);
    }

    public function testSheetWithoutPreservedAttributesKeepsTheMinimalOutput(): void
    {
        $output = $this->rewrite('<link rel="stylesheet" href="https://cdn.test/a.css"/>', true, true);

        $this->assertStringStartsWith(
            '<link rel="preload" as="style" href="https://cdn.test/a.css" data-obsidian-include-sheet/>'
            . '<noscript><link rel="stylesheet" href="https://cdn.test/a.css"/></noscript>',
            $output
        );
        $this->assertStringNotContainsString('=""', $output);
        $this->assertStringNotContainsString('  ', $output);
    }

    public function testAllPrintSheetsEmitNoSwapScript(): void
    {
        $includes = '<link rel="stylesheet" media="print" href="https://cdn.test/one.css"/>'
            . '<link rel="stylesheet" media="print" href="https://cdn.test/two.css"/>'
            . '<script src="https://cdn.test/lib.js"></script>';

        $output = $this->rewrite($includes, true, true);

        $this->assertStringContainsString('<script src="https://cdn.test/lib.js" defer></script>', $output);
        $this->assertStringNotContainsString(self::SWAP_MARKER, $output);
        $this->assertStringNotContainsString('data-obsidian-include-sheet', $output);
    }

    public function testNonStylesheetLinkIsUntouched(): void
    {
        $includes = '<link rel="preload" as="font" href="https://cdn.test/a.woff2" crossorigin/>';

        $this->assertSame($includes, $this->rewrite($includes, true, true));
    }

    private function rewrite(
        string $includes,
        bool $deferScripts = false,
        bool $deferStyles = false,
        bool $obsidianTheme = true
    ): string {
        $detector = $this->createMock(ObsidianThemeDetector::class);
        $detector->method('isActive')->willReturn($obsidianTheme);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnMap([
            [ConfigProvider::HEAD_INCLUDES_DEFER_SCRIPTS, ScopeInterface::SCOPE_STORE, null, $deferScripts],
            [ConfigProvider::HEAD_INCLUDES_DEFER_STYLES, ScopeInterface::SCOPE_STORE, null, $deferStyles],
        ]);

        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->method('renderTag')->willReturn(self::SWAP_MARKER);

        $plugin = new NonBlockingIncludes($detector, $scopeConfig, $secureRenderer);

        return $plugin->afterGetIncludes($this->createMock(Config::class), $includes);
    }
}
