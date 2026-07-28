<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Dev;

use MageObsidian\ModernFrontend\Service\Dev\CheckResult;
use MageObsidian\ModernFrontend\Service\Dev\DevDiagnostics;
use MageObsidian\ModernFrontend\Service\Dev\ProbeResult;
use PHPUnit\Framework\TestCase;

class DevDiagnosticsTest extends TestCase
{
    private DevDiagnostics $diagnostics;

    protected function setUp(): void
    {
        $this->diagnostics = new DevDiagnostics();
    }

    public function testContractMissingIsError(): void
    {
        $r = $this->diagnostics->evaluateContract(false, null, '1.0.0');
        $this->assertSame(CheckResult::STATUS_ERROR, $r->status);
        $this->assertStringContainsString('--generate', $r->hint);
    }

    public function testContractVersionMismatchIsWarn(): void
    {
        $r = $this->diagnostics->evaluateContract(true, '0.9.0', '1.0.0');
        $this->assertSame(CheckResult::STATUS_WARN, $r->status);
        $this->assertStringContainsString('0.9.0', $r->message);
    }

    public function testContractValidIsOk(): void
    {
        $r = $this->diagnostics->evaluateContract(true, '1.0.0', '1.0.0');
        $this->assertTrue($r->isOk());
    }

    public function testHmrForcedOffInProductionIsOk(): void
    {
        $this->assertTrue($this->diagnostics->evaluateHmr('production', false)->isOk());
    }

    public function testHmrDisabledInDeveloperIsWarn(): void
    {
        $r = $this->diagnostics->evaluateHmr('developer', false);
        $this->assertSame(CheckResult::STATUS_WARN, $r->status);
        $this->assertStringContainsString('--enable', $r->hint);
    }

    public function testDevServerNotProbedWhenHmrOff(): void
    {
        $r = $this->diagnostics->evaluateDevServer(false, new ProbeResult(false, 0, '', 'should be ignored'));
        $this->assertTrue($r->isOk());
        $this->assertStringContainsString('Not required', $r->message);
    }

    public function testDevServerUnreachableIsError(): void
    {
        $r = $this->diagnostics->evaluateDevServer(true, new ProbeResult(false, 0, '', 'Connection refused'));
        $this->assertSame(CheckResult::STATUS_ERROR, $r->status);
        $this->assertStringContainsString('Connection refused', $r->message);
        $this->assertSame(DevDiagnostics::DEV_SERVER_HINT, $r->hint);
    }

    public function testDevServerReachableButHtmlIsError(): void
    {
        $r = $this->diagnostics->evaluateDevServer(true, new ProbeResult(true, 200, 'text/html'));
        $this->assertSame(CheckResult::STATUS_ERROR, $r->status);
    }

    public function testDevServerReachableAsJsIsOk(): void
    {
        $r = $this->diagnostics->evaluateDevServer(true, new ProbeResult(true, 200, 'text/javascript'));
        $this->assertTrue($r->isOk());
    }

    public function testEnvMissingVarsIsWarn(): void
    {
        $r = $this->diagnostics->evaluateEnv(['VITE_SERVER_HOST', 'VITE_SERVER_PORT']);
        $this->assertSame(CheckResult::STATUS_WARN, $r->status);
        $this->assertStringContainsString('VITE_SERVER_HOST', $r->message);
    }

    public function testEnvCompleteIsOk(): void
    {
        $this->assertTrue($this->diagnostics->evaluateEnv([])->isOk());
    }

    public function testHasErrorDetectsAnyError(): void
    {
        $results = [
            CheckResult::ok('a', 'x'),
            CheckResult::warn('b', 'y'),
            CheckResult::error('c', 'z'),
        ];
        $this->assertTrue($this->diagnostics->hasError($results));
        $this->assertFalse($this->diagnostics->hasError([CheckResult::ok('a', 'x'), CheckResult::warn('b', 'y')]));
    }

    public function testProbeResultJavaScriptDetection(): void
    {
        $this->assertTrue((new ProbeResult(true, 200, 'application/javascript'))->isJavaScript());
        $this->assertTrue((new ProbeResult(true, 200, 'text/javascript; charset=utf-8'))->isJavaScript());
        $this->assertFalse((new ProbeResult(true, 200, 'text/html'))->isJavaScript());
    }

    public function testShadowsInDirectoryFlagsOtherExtensionsOfTheConfigBase(): void
    {
        $shadows = $this->diagnostics->shadowsInDirectory(
            'module.config.ts',
            ['module.config.js', 'module.config.cjs', 'module.config.mjs', 'index.js', 'theme.source.css']
        );

        $this->assertSame(['module.config.js', 'module.config.cjs', 'module.config.mjs'], $shadows);
    }

    public function testShadowsInDirectoryIgnoresTheExpectedFile(): void
    {
        $shadows = $this->diagnostics->shadowsInDirectory('module.config.ts', ['module.config.ts']);
        $this->assertSame([], $shadows);
    }

    public function testShadowsInDirectoryHonoursTheExpectedExtension(): void
    {
        // theme.config is loaded as .js, so a .ts sibling is the ignored one.
        $shadows = $this->diagnostics->shadowsInDirectory(
            'theme.config.js',
            ['theme.config.js', 'theme.config.ts']
        );
        $this->assertSame(['theme.config.ts'], $shadows);
    }

    public function testShadowsInDirectoryEmptyWhenNoConfigPresent(): void
    {
        $this->assertSame(
            [],
            $this->diagnostics->shadowsInDirectory('module.config.ts', ['app.js', 'styles.css'])
        );
    }

    public function testEvaluateShadowedConfigsEmptyIsOk(): void
    {
        $this->assertTrue($this->diagnostics->evaluateShadowedConfigs([])->isOk());
    }

    public function testEvaluateShadowedConfigsListsFilesAndWarns(): void
    {
        $r = $this->diagnostics->evaluateShadowedConfigs([
            '/app/code/Acme/Catalog/view/frontend/web/module.config.js',
            '/app/design/frontend/Acme/theme/Magento_Theme/web/module.config.cjs',
        ]);

        $this->assertSame(CheckResult::STATUS_WARN, $r->status);
        $this->assertStringContainsString('2 config file(s)', $r->message);
        $this->assertStringContainsString('module.config.js', $r->message);
        $this->assertStringContainsString('module.config.cjs', $r->message);
        $this->assertStringContainsString('module.config.ts', $r->hint);
    }

    public function testResolvePageCacheIdentifierFallsBackToThePreference(): void
    {
        $this->assertSame(
            'Magento\PageCache\Model\App\Request\Http\IdentifierForSave',
            $this->diagnostics->resolvePageCacheIdentifier([
                'preferences' => [
                    DevDiagnostics::PAGE_CACHE_IDENTIFIER_INTERFACE =>
                        'Magento\PageCache\Model\App\Request\Http\IdentifierForSave',
                ],
            ])
        );
    }

    /**
     * @dataProvider kernelArgumentShapes
     */
    public function testResolvePageCacheIdentifierPrefersTheKernelArgument(array $identifierArgument): void
    {
        $resolved = $this->diagnostics->resolvePageCacheIdentifier([
            'preferences' => [
                DevDiagnostics::PAGE_CACHE_IDENTIFIER_INTERFACE =>
                    'Magento\PageCache\Model\App\Request\Http\IdentifierForSave',
            ],
            'arguments' => [
                DevDiagnostics::PAGE_CACHE_KERNEL => ['identifier' => $identifierArgument],
            ],
        ]);

        $this->assertSame(DevDiagnostics::VARY_AWARE_IDENTIFIER, $resolved);
    }

    /**
     * The compiled DI config packs object arguments as `_i_` and points at the
     * generated Interceptor; the uncompiled reader emits `instance`.
     */
    public static function kernelArgumentShapes(): array
    {
        return [
            'compiled, intercepted' => [['_i_' => DevDiagnostics::VARY_AWARE_IDENTIFIER . '\Interceptor']],
            'compiled, plain' => [['_i_' => DevDiagnostics::VARY_AWARE_IDENTIFIER]],
            'uncompiled' => [['instance' => '\\' . DevDiagnostics::VARY_AWARE_IDENTIFIER]],
        ];
    }

    public function testResolvePageCacheIdentifierIgnoresANonInstanceArgument(): void
    {
        $resolved = $this->diagnostics->resolvePageCacheIdentifier([
            'preferences' => [
                DevDiagnostics::PAGE_CACHE_IDENTIFIER_INTERFACE =>
                    'Magento\PageCache\Model\App\Request\Http\IdentifierForSave',
            ],
            'arguments' => [
                DevDiagnostics::PAGE_CACHE_KERNEL => ['identifier' => ['_vn_' => true]],
            ],
        ]);

        $this->assertSame('Magento\PageCache\Model\App\Request\Http\IdentifierForSave', $resolved);
    }

    public function testResolvePageCacheIdentifierReturnsEmptyWhenUnknown(): void
    {
        $this->assertSame('', $this->diagnostics->resolvePageCacheIdentifier([]));
    }

    public function testPageCacheVaryIsOkUnderVarnish(): void
    {
        $r = $this->diagnostics->evaluatePageCacheVary(true, 'Anything\At\All', ['currency']);
        $this->assertTrue($r->isOk());
        $this->assertStringContainsString('Varnish', $r->message);
    }

    public function testPageCacheVaryIsOkWhenTheIdentifierReadsTheCookie(): void
    {
        $r = $this->diagnostics->evaluatePageCacheVary(
            false,
            DevDiagnostics::VARY_AWARE_IDENTIFIER,
            ['currency']
        );
        $this->assertTrue($r->isOk());
    }

    public function testPageCacheVaryWarnsWhenNothingVariesYet(): void
    {
        $r = $this->diagnostics->evaluatePageCacheVary(
            false,
            'Magento\PageCache\Model\App\Request\Http\IdentifierForSave',
            []
        );

        $this->assertSame(CheckResult::STATUS_WARN, $r->status);
        $this->assertStringContainsString(DevDiagnostics::PAGE_CACHE_VARY_ISSUE_URL, $r->hint);
    }

    public function testPageCacheVaryErrorsWhenADimensionVaries(): void
    {
        $r = $this->diagnostics->evaluatePageCacheVary(
            false,
            'Magento\PageCache\Model\App\Request\Http\IdentifierForSave',
            ['currency']
        );

        $this->assertSame(CheckResult::STATUS_ERROR, $r->status);
        $this->assertStringContainsString('currency', $r->message);
        $this->assertStringContainsString(DevDiagnostics::VARY_AWARE_IDENTIFIER, $r->hint);
        $this->assertStringContainsString(DevDiagnostics::PAGE_CACHE_VARY_ISSUE_URL, $r->hint);
    }

    public function testEagerIslandWithoutServerHtmlIsReported(): void
    {
        $source = '{{ render_vue("Vendor_Module::catalog/ProductForm", { id: 1 }, true) }}';

        $this->assertSame(
            ['Vendor_Module::catalog/ProductForm'],
            $this->diagnostics->eagerIslandsWithoutHydration($source)
        );
    }

    public function testEagerIslandThatHydratesIsNotReported(): void
    {
        $source = '{{ render_vue("Vendor::Card", {}, true, serverHtml, true) }}';

        $this->assertSame([], $this->diagnostics->eagerIslandsWithoutHydration($source));
    }

    public function testAPlaceholderAloneStillCountsAsShifting(): void
    {
        // Markup Vue throws away on mount reflows just like an empty container.
        $source = '{{ render_vue("Vendor::Card", {}, true, placeholder) }}';

        $this->assertSame(['Vendor::Card'], $this->diagnostics->eagerIslandsWithoutHydration($source));
    }

    public function testLazyIslandsAreNeverReported(): void
    {
        // A visible island mounts below the fold, where nothing has painted yet.
        $this->assertSame([], $this->diagnostics->eagerIslandsWithoutHydration(
            '{{ render_vue("Vendor::Card", {}, false) }}{{ render_vue("Vendor::Other", {}) }}'
        ));
    }

    public function testThePhtmlSpellingIsRecognisedToo(): void
    {
        $source = '<?= $block->renderVueComponent("Vendor::Card", [], true) ?>';

        $this->assertSame(['Vendor::Card'], $this->diagnostics->eagerIslandsWithoutHydration($source));
    }

    public function testCommasInsideArgumentsDoNotShiftTheArgumentPositions(): void
    {
        $source = '{{ render_vue("Vendor::Card", { a: fn(1, 2), b: [3, 4], c: "x, y" }, true) }}';

        $this->assertSame(['Vendor::Card'], $this->diagnostics->eagerIslandsWithoutHydration($source));
    }

    public function testACallSplitOverSeveralLinesIsStillParsed(): void
    {
        $source = "{{ render_vue(\n    'Vendor::Card',\n    { a: 1 },\n    true\n) }}";

        $this->assertSame(['Vendor::Card'], $this->diagnostics->eagerIslandsWithoutHydration($source));
    }

    public function testEveryIslandHydratingMeansAHealthyCheck(): void
    {
        $result = $this->diagnostics->evaluateIslandHydration(['a.twig' => [], 'b.twig' => []]);

        $this->assertSame('ok', $result->status);
    }

    public function testIslandsWithoutServerHtmlWarnAndNameTemplateAndComponent(): void
    {
        $result = $this->diagnostics->evaluateIslandHydration([
            'product/view.twig' => ['Vendor::ProductForm', 'Vendor::Gallery'],
        ]);

        $this->assertSame('warn', $result->status);
        $this->assertStringContainsString('2 eager island(s)', $result->message);
        $this->assertStringContainsString('product/view.twig', $result->message);
        $this->assertStringContainsString('Vendor::ProductForm', $result->message);
        $this->assertStringContainsString('mage-obsidian:island-ssr', $result->hint);
    }
}
