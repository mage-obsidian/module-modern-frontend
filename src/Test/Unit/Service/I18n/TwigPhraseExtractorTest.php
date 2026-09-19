<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\I18n;

use MageObsidian\ModernFrontend\Service\I18n\TwigPhraseExtractor;
use PHPUnit\Framework\TestCase;

class TwigPhraseExtractorTest extends TestCase
{
    private TwigPhraseExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new TwigPhraseExtractor();
    }

    public function testExtractsSingleAndDoubleQuotedPhrases(): void
    {
        $source = <<<'TWIG'
        <a>{{ __('Add to Cart') }}</a>
        <p>{{ __("Your cart is empty") }}</p>
        TWIG;

        $this->assertSame(
            ['Add to Cart', 'Your cart is empty'],
            $this->extractor->extractFromString($source)
        );
    }

    public function testKeepsPlaceholdersAndDropsTheArgumentsThatFillThem(): void
    {
        $source = "{{ __('Items %1 to %2 of %3', first, last, total) }}";

        $this->assertSame(['Items %1 to %2 of %3'], $this->extractor->extractFromString($source));
    }

    public function testReadsACallSpreadOverSeveralLines(): void
    {
        $source = <<<'TWIG'
        {{ __(
            'We could not reach the shipping estimator',
            reason
        ) }}
        TWIG;

        $this->assertSame(
            ['We could not reach the shipping estimator'],
            $this->extractor->extractFromString($source)
        );
    }

    public function testUnescapesTheDelimiterAndTheBackslash(): void
    {
        $source = <<<'TWIG'
        {{ __('It\'s on its way') }}
        {{ __("She said \"yes\"") }}
        {{ __('A back\\slash') }}
        TWIG;

        $this->assertSame(
            ["It's on its way", 'She said "yes"', 'A back\\slash'],
            $this->extractor->extractFromString($source)
        );
    }

    public function testTakesTheOppositeQuoteAsPartOfThePhrase(): void
    {
        $source = '{{ __("It\'s here") }}';

        $this->assertSame(["It's here"], $this->extractor->extractFromString($source));
    }

    public function testReadsCallsInsideStatementsAndFilters(): void
    {
        $source = <<<'TWIG'
        {% set label = __('Continue shopping') %}
        {{ __('Sign in')|upper }}
        {% if total %}{{ __('Order total') }}{% endif %}
        TWIG;

        $this->assertSame(
            ['Continue shopping', 'Sign in', 'Order total'],
            $this->extractor->extractFromString($source)
        );
    }

    public function testReadsOnlyTheFirstPhraseOfEachCallAndDeduplicates(): void
    {
        $source = <<<'TWIG'
        {{ __('Save') }}
        {{ __('Save') }}
        {{ label ? __('Save') : __('Discard') }}
        TWIG;

        $this->assertSame(['Save', 'Discard'], $this->extractor->extractFromString($source));
    }

    public function testIgnoresTextOutsideAnExpression(): void
    {
        $source = <<<'TWIG'
        <p>Write it as __('Example phrase') in a template.</p>
        <pre>{{ __('Shown in the guide') }}</pre>
        TWIG;

        $this->assertSame([], $this->extractor->extractFromString(explode("\n", $source)[0]));
        $this->assertSame(['Shown in the guide'], $this->extractor->extractFromString(explode("\n", $source)[1]));
    }

    public function testIgnoresTwigComments(): void
    {
        $source = <<<'TWIG'
        {# {{ __('Commented out') }} #}
        {# __('Bare in a comment') #}
        {{ __('Live phrase') }}
        TWIG;

        $this->assertSame(['Live phrase'], $this->extractor->extractFromString($source));
    }

    public function testIgnoresEverythingInsideVerbatim(): void
    {
        $source = <<<'TWIG'
        {% verbatim %}
            {{ __('Documented, not translated') }}
        {% endverbatim %}
        {{ __('After the example') }}
        TWIG;

        $this->assertSame(['After the example'], $this->extractor->extractFromString($source));
    }

    public function testIgnoresACallQuotedInsideAnotherString(): void
    {
        $source = <<<'TWIG'
        {{ hint("write __('Not a phrase') here") }}
        {{ __('A real one') }}
        TWIG;

        $this->assertSame(['A real one'], $this->extractor->extractFromString($source));
    }

    public function testIgnoresDynamicFirstArguments(): void
    {
        $source = <<<'TWIG'
        {{ __(label) }}
        {{ __('Hello ' ~ name) }}
        {{ __(message|trim) }}
        {{ __("Hello #{name}") }}
        TWIG;

        $this->assertSame([], $this->extractor->extractFromString($source));
    }

    public function testIgnoresAMethodNamedLikeTheTranslator(): void
    {
        $source = "{{ helper.__('Not the translator') }}{{ prefix__('Nor this') }}";

        $this->assertSame([], $this->extractor->extractFromString($source));
    }

    public function testIgnoresAnEmptyPhrase(): void
    {
        $this->assertSame([], $this->extractor->extractFromString("{{ __('') }}"));
    }

    public function testDoesNotEndTheExpressionOnADelimiterInsideAString(): void
    {
        $source = "{{ __('Closes with }} inside') }}{{ __('And after') }}";

        $this->assertSame(['Closes with }} inside', 'And after'], $this->extractor->extractFromString($source));
    }

    public function testReadsThroughWhitespaceControlMarkers(): void
    {
        $source = "{%- set a = __('Trimmed statement') -%}{{- __('Trimmed output') -}}";

        $this->assertSame(['Trimmed statement', 'Trimmed output'], $this->extractor->extractFromString($source));
    }

    public function testReturnsNothingForATemplateWithoutCalls(): void
    {
        $this->assertSame([], $this->extractor->extractFromString('<div class="pdp">{{ product.name }}</div>'));
    }
}
