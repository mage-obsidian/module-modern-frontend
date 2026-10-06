<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service;

use Magento\Framework\Module\Dir\Reader;
use MageObsidian\ModernFrontend\Service\RuntimeScriptReader;
use PHPUnit\Framework\TestCase;

class RuntimeScriptReaderTest extends TestCase
{
    private string $viewDir;

    protected function setUp(): void
    {
        $this->viewDir = sys_get_temp_dir() . '/runtime-script-reader-' . uniqid();
        if (!class_exists(Reader::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
        mkdir($this->viewDir . '/frontend/runtime', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->viewDir . '/frontend/runtime/*') ?: []);
        @rmdir($this->viewDir . '/frontend/runtime');
        @rmdir($this->viewDir . '/frontend');
        @rmdir($this->viewDir);
    }

    public function testTheLicenseHeaderStaysOutOfTheInlinedScript(): void
    {
        $this->write(
            "// This file is part of the MageObsidian - ModernFrontend project.\n"
            . "//\n"
            . "// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez\n"
            . "// SPDX-License-Identifier: MIT\n"
            . "(function () {\n    var url = \"https://example.test//path\";\n})();\n"
        );

        $this->assertSame(
            "(function () {\n    var url = \"https://example.test//path\";\n})();\n",
            $this->reader()->read('Vendor_Module', '/frontend/runtime/probe.head.js')
        );
    }

    public function testAScriptWithoutAHeaderIsInlinedAsWritten(): void
    {
        $this->write("(function () {\n    // kept\n})();\n");

        $this->assertSame(
            "(function () {\n    // kept\n})();\n",
            $this->reader()->read('Vendor_Module', '/frontend/runtime/probe.head.js')
        );
    }

    public function testAMissingScriptReadsAsEmpty(): void
    {
        $this->assertSame('', $this->reader()->read('Vendor_Module', '/frontend/runtime/absent.head.js'));
    }

    private function write(string $contents): void
    {
        file_put_contents($this->viewDir . '/frontend/runtime/probe.head.js', $contents);
    }

    private function reader(): RuntimeScriptReader
    {
        $moduleReader = $this->createStub(Reader::class);
        $moduleReader->method('getModuleDir')->willReturn($this->viewDir);

        return new RuntimeScriptReader($moduleReader);
    }
}
