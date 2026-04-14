<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\SchemaOrg;

use InvalidArgumentException;
use MageObsidian\ModernFrontend\Model\SchemaOrg\JsonLdRenderer;
use PHPUnit\Framework\TestCase;

class JsonLdRendererTest extends TestCase
{
    private JsonLdRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new JsonLdRenderer();
    }

    public function testWrapsNodeInScriptTagAndInjectsContextFirst(): void
    {
        $html = $this->renderer->render(['@type' => 'Organization', 'name' => 'Acme']);

        $this->assertSame(
            '<script type="application/ld+json">'
            . '{"@context":"https://schema.org","@type":"Organization","name":"Acme"}'
            . '</script>',
            $html
        );
    }

    public function testEmptyNodeRendersNothing(): void
    {
        $this->assertSame('', $this->renderer->render([]));
    }

    public function testThrowsWhenNonEmptyNodeHasNoType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('@type');

        $this->renderer->render(['name' => 'Acme']);
    }

    public function testEscapesScriptBreakoutCharacters(): void
    {
        // A "</script>" embedded in data must not close the surrounding tag:
        // JSON_HEX_TAG turns the angle brackets into < / >.
        $html = $this->renderer->render([
            '@type' => 'Organization',
            'name' => '</script><img src=x onerror=alert(1)>',
        ]);

        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertStringContainsString('<', $html);

        // The single <script> wrapper is the only literal tag in the output.
        $this->assertSame(1, substr_count($html, '<script'));
        $this->assertSame(1, substr_count($html, '</script>'));
    }

    public function testKeepsUnicodeReadable(): void
    {
        $html = $this->renderer->render(['@type' => 'Organization', 'name' => 'Café Münch']);

        // JSON_UNESCAPED_UNICODE keeps accented characters literal (better for SEO/readability).
        $this->assertStringContainsString('Café Münch', $html);
    }

    public function testRenderManyConcatenatesAndSkipsEmptyNodes(): void
    {
        $html = $this->renderer->renderMany([
            ['@type' => 'Organization', 'name' => 'Acme'],
            [],
            ['@type' => 'WebSite', 'name' => 'Acme'],
        ]);

        $this->assertSame(2, substr_count($html, '<script'));
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringContainsString('"@type":"WebSite"', $html);
    }

    public function testThrowsOnUnencodableNode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Malformed UTF-8 cannot be JSON-encoded.
        $this->renderer->render(['@type' => 'Organization', 'name' => "\xB1\x31"]);
    }
}
