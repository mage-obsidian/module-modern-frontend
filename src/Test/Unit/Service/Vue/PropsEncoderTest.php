<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Vue;

use InvalidArgumentException;
use MageObsidian\ModernFrontend\Service\Vue\PropsEncoder;
use PHPUnit\Framework\TestCase;

class PropsEncoderTest extends TestCase
{
    public function testEncodesPlainPropsAsJson(): void
    {
        $this->assertSame(
            '{"label":"Add","count":3}',
            PropsEncoder::encode('Vendor::Component', ['label' => 'Add', 'count' => 3])
        );
    }

    public function testEmptyPropsEncodeAsEmptyObject(): void
    {
        // [] would encode as "[]", which Vue accepts, but the call must not throw.
        $this->assertSame('[]', PropsEncoder::encode('Vendor::Component', []));
    }

    public function testEscapesHtmlAndScriptBreakoutCharacters(): void
    {
        $payload = '</script><img src=x onerror=alert(1)>';
        $encoded = PropsEncoder::encode('Vendor::Component', [
            'html' => $payload,
            'quote' => '"\'&',
        ]);

        // The HEX flags turn <, >, &, ' and " into \u00XX escapes, so no literal
        // breakout sequence survives in the inlined payload.
        $this->assertStringNotContainsString('</script>', $encoded);
        $this->assertStringNotContainsString('<img', $encoded);
        $this->assertStringNotContainsString('<', $encoded);
        $this->assertStringNotContainsString('&', $encoded);

        // It is still valid JSON that decodes back to the original values.
        $decoded = json_decode($encoded, true);
        $this->assertSame($payload, $decoded['html']);
        $this->assertSame('"\'&', $decoded['quote']);
    }

    public function testThrowsOnUnencodableProps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vendor::Broken');

        // A malformed UTF-8 byte sequence cannot be JSON-encoded.
        PropsEncoder::encode('Vendor::Broken', ['bad' => "\xB1\x31"]);
    }
}
