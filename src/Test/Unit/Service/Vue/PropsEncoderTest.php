<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Vue;

use InvalidArgumentException;
use MageObsidian\ModernFrontend\Service\Vue\PropsEncoder;
use PHPUnit\Framework\TestCase;

class PropsEncoderTest extends TestCase
{
    public function testEncodesPlainPropsAsAttributeSafeJson(): void
    {
        // Structural quotes are entity-escaped so the JSON is safe inside a
        // double-quoted HTML attribute.
        $this->assertSame(
            '{&quot;label&quot;:&quot;Add&quot;,&quot;count&quot;:3}',
            PropsEncoder::encodeAttribute('Vendor::Component', ['label' => 'Add', 'count' => 3])
        );
    }

    public function testEmptyPropsEncodeAsEmptyArray(): void
    {
        // [] encodes as "[]" (no special characters to escape); must not throw.
        $this->assertSame('[]', PropsEncoder::encodeAttribute('Vendor::Component', []));
    }

    public function testEscapesHtmlAndAttributeBreakoutCharacters(): void
    {
        $payload = '</div><img src=x onerror=alert(1)>';
        $encoded = PropsEncoder::encodeAttribute('Vendor::Component', [
            'html' => $payload,
            'quote' => '"\'&',
        ]);

        // No literal <, >, ", or ' survives to break out of the attribute.
        $this->assertStringNotContainsString('<', $encoded);
        $this->assertStringNotContainsString('>', $encoded);
        $this->assertStringNotContainsString('"', $encoded);
        $this->assertStringNotContainsString("'", $encoded);

        // Decoding the entities (as the browser does) yields the original values.
        $decoded = json_decode(htmlspecialchars_decode($encoded, ENT_QUOTES), true);
        $this->assertSame($payload, $decoded['html']);
        $this->assertSame('"\'&', $decoded['quote']);
    }

    public function testThrowsOnUnencodableProps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vendor::Broken');

        // A malformed UTF-8 byte sequence cannot be JSON-encoded.
        PropsEncoder::encodeAttribute('Vendor::Broken', ['bad' => "\xB1\x31"]);
    }
}
