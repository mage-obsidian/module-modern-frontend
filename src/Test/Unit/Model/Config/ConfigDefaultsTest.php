<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Config;

use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use PHPUnit\Framework\TestCase;

final class ConfigDefaultsTest extends TestCase
{
    private const string CONFIG_FILE = __DIR__ . '/../../../../etc/config.xml';

    public function testHmrShipsDisabled(): void
    {
        $config = simplexml_load_file(self::CONFIG_FILE);
        $value = $config->xpath('default/' . ConfigProvider::HMR_ENABLED);

        self::assertCount(1, $value);
        self::assertSame('0', trim((string)$value[0]));
    }
}
