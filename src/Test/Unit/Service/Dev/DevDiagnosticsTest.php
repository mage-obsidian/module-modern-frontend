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
}
