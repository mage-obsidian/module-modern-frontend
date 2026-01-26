<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\I18n;

use MageObsidian\ModernFrontend\Service\I18n\VuePhraseExtractor;
use PHPUnit\Framework\TestCase;

class VuePhraseExtractorTest extends TestCase
{
    private VuePhraseExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new VuePhraseExtractor();
    }

    public function testExtractsSingleAndDoubleQuotedPhrases(): void
    {
        $source = <<<'VUE'
        <template><span>{{ $t('Add to cart') }}</span><p>{{ $t("Your cart is empty") }}</p></template>
        VUE;

        $this->assertSame(
            ['Add to cart', 'Your cart is empty'],
            $this->extractor->extractFromString($source)
        );
    }

    public function testIgnoresPlaceholderArguments(): void
    {
        $source = "const msg = \$t('Remove %1', product.name);";

        $this->assertSame(['Remove %1'], $this->extractor->extractFromString($source));
    }

    public function testDeduplicatesKeepingFirstSeenOrder(): void
    {
        $source = "\$t('B'); \$t('A'); \$t('B');";

        $this->assertSame(['B', 'A'], $this->extractor->extractFromString($source));
    }

    public function testHandlesEscapedQuoteInsidePhrase(): void
    {
        $source = "\$t('It\\'s here');";

        $this->assertSame(["It's here"], $this->extractor->extractFromString($source));
    }

    public function testReturnsEmptyArrayWhenNoCalls(): void
    {
        $this->assertSame([], $this->extractor->extractFromString('const x = translate("nope");'));
    }

    public function testSkipsEmptyPhrase(): void
    {
        $this->assertSame([], $this->extractor->extractFromString("\$t('')"));
    }
}
