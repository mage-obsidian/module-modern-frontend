<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Contract;

use MageObsidian\ModernFrontend\Service\Contract\ContractFileRefresher;
use PHPUnit\Framework\TestCase;

class ContractFileRefresherTest extends TestCase
{
    public function testAnUnrestrictedApiIsAlwaysAllowed(): void
    {
        $this->assertTrue(ContractFileRefresher::apiAllowed('', '/var/www/html/pub/index.php'));
    }

    public function testAScriptInsideTheRestrictedPathIsAllowed(): void
    {
        $this->assertTrue(ContractFileRefresher::apiAllowed('/var/www/html', '/var/www/html/pub/index.php'));
    }

    public function testAScriptOutsideTheRestrictedPathIsNotAllowed(): void
    {
        $this->assertFalse(ContractFileRefresher::apiAllowed('/opt/admin', '/var/www/html/pub/index.php'));
    }

    public function testRefreshingAFileOpcacheNeverCompiledIsHarmless(): void
    {
        (new ContractFileRefresher())->refresh(sys_get_temp_dir() . '/mage-obsidian-missing-contract.php');

        $this->addToAssertionCount(1);
    }
}
