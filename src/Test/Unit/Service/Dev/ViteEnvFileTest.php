<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Dev;

use MageObsidian\ModernFrontend\Service\Dev\ViteEnvFile;
use PHPUnit\Framework\TestCase;

class ViteEnvFileTest extends TestCase
{
    public function testBuildVarsMapsEverythingInOrder(): void
    {
        $vars = ViteEnvFile::buildVars('php-noxdebug', '5173', true, '/__vite_ping', 'shop.test', 'shop.test,localhost');

        $this->assertSame(
            [
                ViteEnvFile::VAR_SERVER_HOST,
                ViteEnvFile::VAR_SERVER_PORT,
                ViteEnvFile::VAR_SERVER_SECURE,
                ViteEnvFile::VAR_HMR_PATH,
                ViteEnvFile::VAR_PUBLIC_HOST,
                ViteEnvFile::VAR_ALLOWED_HOSTS,
            ],
            array_keys($vars)
        );
        $this->assertSame('php-noxdebug', $vars[ViteEnvFile::VAR_SERVER_HOST]);
        $this->assertSame('5173', $vars[ViteEnvFile::VAR_SERVER_PORT]);
        $this->assertSame('/__vite_ping', $vars[ViteEnvFile::VAR_HMR_PATH]);
        $this->assertSame('shop.test', $vars[ViteEnvFile::VAR_PUBLIC_HOST]);
        $this->assertSame('shop.test,localhost', $vars[ViteEnvFile::VAR_ALLOWED_HOSTS]);
    }

    public function testSecureSerializesToTrueFalseTokens(): void
    {
        $this->assertSame('true', ViteEnvFile::buildVars('h', '1', true, '/p', 'h', 'h')[ViteEnvFile::VAR_SERVER_SECURE]);
        $this->assertSame('false', ViteEnvFile::buildVars('h', '1', false, '/p', 'h', 'h')[ViteEnvFile::VAR_SERVER_SECURE]);
    }

    public function testRenderEmitsKeyValueLines(): void
    {
        $body = ViteEnvFile::render([
            ViteEnvFile::VAR_SERVER_HOST => 'php-noxdebug',
            ViteEnvFile::VAR_SERVER_PORT => '5173',
        ]);

        $this->assertSame("VITE_SERVER_HOST=php-noxdebug\nVITE_SERVER_PORT=5173\n", $body);
    }

    public function testRenderLeavesPlainCommaListUnquoted(): void
    {
        $body = ViteEnvFile::render([ViteEnvFile::VAR_ALLOWED_HOSTS => 'shop.test,localhost']);

        $this->assertSame("VITE_SERVER_ALLOWED_HOSTS=shop.test,localhost\n", $body);
    }

    public function testRenderQuotesValuesWithWhitespaceOrSpecials(): void
    {
        $body = ViteEnvFile::render([
            'A' => 'has space',
            'B' => 'has#hash',
        ]);

        $this->assertStringContainsString('A="has space"', $body);
        $this->assertStringContainsString('B="has#hash"', $body);
    }

    public function testRenderEscapesEmbeddedQuotesAndBackslashes(): void
    {
        $body = ViteEnvFile::render(['A' => 'a "quote" and \\ slash']);

        $this->assertSame('A="a \\"quote\\" and \\\\ slash"' . "\n", $body);
    }

    public function testRenderEmptyMapIsEmptyString(): void
    {
        $this->assertSame('', ViteEnvFile::render([]));
    }

    public function testRenderEmptyValueStaysUnquoted(): void
    {
        $this->assertSame("A=\n", ViteEnvFile::render(['A' => '']));
    }

    public function testRoundTripBuildThenRenderIsParseable(): void
    {
        $vars = ViteEnvFile::buildVars('php-noxdebug', '5173', false, '/__vite_ping', 'shop.test', 'shop.test,localhost');
        $body = ViteEnvFile::render($vars);

        $parsed = [];
        foreach (explode("\n", trim($body)) as $line) {
            [$k, $v] = explode('=', $line, 2);
            $parsed[$k] = $v;
        }

        $this->assertSame($vars, $parsed);
    }
}
