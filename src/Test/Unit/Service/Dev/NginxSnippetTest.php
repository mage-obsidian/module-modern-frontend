<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Dev;

use MageObsidian\ModernFrontend\Service\Dev\NginxSnippet;
use PHPUnit\Framework\TestCase;

class NginxSnippetTest extends TestCase
{
    public function testRenderInjectsUpstreamFromHostAndPort(): void
    {
        $snippet = NginxSnippet::render('php-noxdebug', '5173');

        $this->assertStringContainsString('http://php-noxdebug:5173', $snippet);
        $this->assertSame(2, substr_count($snippet, 'set $vite_upstream http://php-noxdebug:5173;'));
    }

    public function testRenderProxiesViteEndpointsAndGeneratedPaths(): void
    {
        $snippet = NginxSnippet::render('localhost', '5173');

        $this->assertStringContainsString('@vite', $snippet);
        $this->assertStringContainsString('node_modules', $snippet);
        $this->assertStringContainsString('vite_generated', $snippet);
    }

    public function testRenderEmitsRealProxyPassNotDebugReturn(): void
    {
        $snippet = NginxSnippet::render('localhost', '5173');

        $this->assertStringContainsString('proxy_pass $vite_upstream;', $snippet);
        $this->assertStringNotContainsString('return 598', $snippet);
    }

    public function testRenderKeepsWebsocketUpgradeHeaders(): void
    {
        $snippet = NginxSnippet::render('localhost', '5173');

        $this->assertStringContainsString('proxy_set_header Upgrade $http_upgrade;', $snippet);
        $this->assertStringContainsString('proxy_set_header Connection "upgrade";', $snippet);
    }
}
