<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\I18n;

use MageObsidian\ModernFrontend\Service\I18n\CsvDictionary;
use PHPUnit\Framework\TestCase;

class CsvDictionaryTest extends TestCase
{
    private CsvDictionary $csv;

    protected function setUp(): void
    {
        $this->csv = new CsvDictionary();
    }

    public function testParseReadsPhraseTranslationPairs(): void
    {
        $contents = "\"Add to cart\",\"Añadir al carrito\"\n\"Cancel\",\"Cancelar\"\n";

        $this->assertSame(
            ['Add to cart' => 'Añadir al carrito', 'Cancel' => 'Cancelar'],
            $this->csv->parse($contents)
        );
    }

    public function testParseMapsPhraseToItselfWhenTranslationMissing(): void
    {
        $this->assertSame(['Lonely' => 'Lonely'], $this->csv->parse('"Lonely"'));
    }

    public function testParseSkipsBlankLines(): void
    {
        $this->assertSame(['A' => 'B'], $this->csv->parse("\n\"A\",\"B\"\n\n"));
    }

    public function testMergePreservesExistingTranslationsAndAddsNewAsIdentity(): void
    {
        $existing = ['Cancel' => 'Cancelar'];

        $this->assertSame(
            ['Cancel' => 'Cancelar', 'Save' => 'Save'],
            $this->csv->merge($existing, ['Cancel', 'Save'])
        );
    }

    public function testRenderSortsByPhraseAndQuotes(): void
    {
        $rendered = $this->csv->render(['Zeta' => 'Zeta', 'Alpha' => 'Alpha']);

        $this->assertSame("\"Alpha\",\"Alpha\"\n\"Zeta\",\"Zeta\"\n", $rendered);
    }

    public function testRenderRoundTripsThroughParse(): void
    {
        $dictionary = ['Add %1' => 'Añadir %1', 'Close' => 'Cerrar'];

        $this->assertSame($dictionary, $this->csv->parse($this->csv->render($dictionary)));
    }

    public function testCountNewIgnoresKnownAndDuplicatePhrases(): void
    {
        $existing = ['Known' => 'Known'];

        $this->assertSame(1, $this->csv->countNew($existing, ['Known', 'Fresh', 'Fresh']));
    }
}
