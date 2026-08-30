<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\View\Page\Config;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class NonBlockingIncludesDefaultsTest extends TestCase
{
    private const string CONFIG_XML = __DIR__ . '/../../../../../../etc/config.xml';

    public function testConfigXmlIsReadable(): void
    {
        $this->assertFileExists(self::CONFIG_XML);
    }

    public function testShippedDefaultsLeaveBothFlagsOff(): void
    {
        $head = $this->headDefaults();

        $this->assertSame('0', (string)$head->includes_defer_scripts);
        $this->assertSame('0', (string)$head->includes_defer_styles);
    }

    public function testBothFlagsArePresentInTheShippedDefaults(): void
    {
        $head = $this->headDefaults();

        $this->assertCount(1, $head->includes_defer_scripts);
        $this->assertCount(1, $head->includes_defer_styles);
    }

    private function headDefaults(): SimpleXMLElement
    {
        $xml = new SimpleXMLElement((string)file_get_contents(self::CONFIG_XML));
        $head = $xml->xpath('/config/default/mage_obsidian/head');

        $this->assertIsArray($head);
        $this->assertCount(1, $head);

        return $head[0];
    }
}
