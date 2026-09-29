<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Contract;

use MageObsidian\ModernFrontend\Service\Contract\ContractPaths;
use PHPUnit\Framework\TestCase;

class ContractPathsTest extends TestCase
{
    public function testAPathInsideTheRootBecomesRelative(): void
    {
        $this->assertSame('app/design/frontend/Acme/shop', ContractPaths::relative('/var/www/html/app/design/frontend/Acme/shop', '/var/www/html'));
    }

    public function testATrailingSlashOnTheRootMakesNoDifference(): void
    {
        $this->assertSame('vendor/acme/mod', ContractPaths::relative('/var/www/html/vendor/acme/mod', '/var/www/html/'));
    }

    public function testAPathOutsideTheRootStaysAbsolute(): void
    {
        $this->assertSame('/home/dev/repos/mod', ContractPaths::relative('/home/dev/repos/mod', '/var/www/html'));
    }

    public function testASiblingThatSharesThePrefixStaysAbsolute(): void
    {
        $this->assertSame('/var/www/html2/mod', ContractPaths::relative('/var/www/html2/mod', '/var/www/html'));
    }

    public function testARelativePathResolvesAgainstTheRoot(): void
    {
        $this->assertSame('/srv/app/vendor/acme/mod', ContractPaths::absolute('vendor/acme/mod', '/srv/app'));
    }

    public function testAnAbsolutePathIsKeptAsIs(): void
    {
        $this->assertSame('/home/dev/repos/mod', ContractPaths::absolute('/home/dev/repos/mod', '/srv/app'));
    }

    public function testRelativizeTouchesOnlyModuleAndThemeSources(): void
    {
        $contract = [
            'schema_version' => '1.1.0',
            'modules' => ['Acme_Mod' => ['src' => '/r/vendor/acme/mod', 'universal' => true]],
            'themes' => ['Acme/shop' => ['src' => '/r/app/design/frontend/Acme/shop', 'parent' => null]],
            'LIB_PATH' => 'lib',
        ];

        $this->assertSame(
            [
                'schema_version' => '1.1.0',
                'modules' => ['Acme_Mod' => ['src' => 'vendor/acme/mod', 'universal' => true]],
                'themes' => ['Acme/shop' => ['src' => 'app/design/frontend/Acme/shop', 'parent' => null]],
                'LIB_PATH' => 'lib',
            ],
            ContractPaths::relativize($contract, '/r')
        );
    }

    public function testAbsolutizeUndoesRelativize(): void
    {
        $contract = [
            'modules' => ['Acme_Mod' => ['src' => '/r/vendor/acme/mod'], 'Ext_Mod' => ['src' => '/elsewhere/mod']],
            'themes' => [],
        ];

        $this->assertSame($contract, ContractPaths::absolutize(ContractPaths::relativize($contract, '/r'), '/r'));
    }

    public function testAnOldContractWithAbsolutePathsReadsUnchanged(): void
    {
        $contract = ['modules' => ['Acme_Mod' => ['src' => '/old/root/vendor/acme/mod']], 'themes' => []];

        $this->assertSame($contract, ContractPaths::absolutize($contract, '/new/root'));
    }
}
