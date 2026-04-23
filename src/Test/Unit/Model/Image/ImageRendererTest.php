<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Image;

use InvalidArgumentException;
use MageObsidian\ModernFrontend\Model\Image\ImageRenderer;
use PHPUnit\Framework\TestCase;

class ImageRendererTest extends TestCase
{
    private ImageRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ImageRenderer();
    }

    public function testRendersImgWithDimensionsAndSafeDefaults(): void
    {
        $html = $this->renderer->render('https://acme.test/a.jpg', [
            'alt' => 'A bag',
            'width' => 800,
            'height' => 600,
        ]);

        $this->assertSame(
            '<img src="https://acme.test/a.jpg" alt="A bag" width="800" height="600"'
            . ' loading="lazy" decoding="async">',
            $html
        );
    }

    public function testEagerLoadingWhenFetchpriorityHigh(): void
    {
        // The LCP image must not be lazy-loaded.
        $html = $this->renderer->render('https://acme.test/hero.jpg', [
            'width' => 1200,
            'height' => 600,
            'fetchpriority' => 'high',
        ]);

        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
    }

    public function testExplicitLoadingOverridesFetchpriorityDefault(): void
    {
        $html = $this->renderer->render('https://acme.test/hero.jpg', [
            'fetchpriority' => 'high',
            'loading' => 'lazy',
        ]);

        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function testWrapsInPictureWhenSourcesProvided(): void
    {
        $html = $this->renderer->render('https://acme.test/a.jpg', [
            'width' => 800,
            'height' => 600,
            'sources' => [
                ['srcset' => 'https://acme.test/a.avif', 'type' => 'image/avif'],
                ['srcset' => 'https://acme.test/a.webp', 'type' => 'image/webp'],
            ],
        ]);

        $this->assertStringStartsWith('<picture><source srcset="https://acme.test/a.avif" type="image/avif">', $html);
        $this->assertStringContainsString('<source srcset="https://acme.test/a.webp" type="image/webp">', $html);
        $this->assertStringContainsString('<img src="https://acme.test/a.jpg"', $html);
        $this->assertStringEndsWith('</picture>', $html);
    }

    public function testEmitsSrcsetSizesClassAndExtraAttributes(): void
    {
        $html = $this->renderer->render('https://acme.test/a.jpg', [
            'srcset' => 'a-400.jpg 400w, a-800.jpg 800w',
            'sizes' => '(max-width: 600px) 400px, 800px',
            'class' => 'product-image',
            'attributes' => ['id' => 'main-image', 'data-role' => 'zoom'],
        ]);

        $this->assertStringContainsString('srcset="a-400.jpg 400w, a-800.jpg 800w"', $html);
        $this->assertStringContainsString('sizes="(max-width: 600px) 400px, 800px"', $html);
        $this->assertStringContainsString('class="product-image"', $html);
        $this->assertStringContainsString('id="main-image"', $html);
        $this->assertStringContainsString('data-role="zoom"', $html);
    }

    public function testEscapesAttributeBreakoutCharacters(): void
    {
        $html = $this->renderer->render('https://acme.test/a.jpg?x=1&y=2', [
            'alt' => 'Say "hi" <b>',
        ]);

        // No raw quote/angle survives inside the attributes.
        $this->assertStringContainsString('alt="Say &quot;hi&quot; &lt;b&gt;"', $html);
        $this->assertStringContainsString('src="https://acme.test/a.jpg?x=1&amp;y=2"', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function testOmitsUnsetOptionalAttributes(): void
    {
        $html = $this->renderer->render('https://acme.test/a.jpg');

        $this->assertStringNotContainsString('width=', $html);
        $this->assertStringNotContainsString('height=', $html);
        $this->assertStringNotContainsString('fetchpriority=', $html);
        $this->assertStringNotContainsString('<picture>', $html);
        // alt is always present (valid HTML), even if empty.
        $this->assertStringContainsString('alt=""', $html);
    }

    public function testThrowsOnEmptySrc(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->renderer->render('');
    }
}
