<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Model\Cms;

use MageObsidian\ModernFrontend\Model\Cms\IslandDirective;
use MageObsidian\ModernFrontend\Service\Cms\IslandRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The directive only decides where the component name comes from; rendering
 * belongs to IslandRenderer. Both spellings have to work, because the positional
 * one reads better and the named one is what an author copies from the docs.
 */
class IslandDirectiveTest extends TestCase
{
    private const string COMPONENT = 'Vendor_Module::catalog/ProductCarousel';

    private IslandRenderer&MockObject $renderer;
    private IslandDirective $directive;

    protected function setUp(): void
    {
        $this->renderer = $this->createMock(IslandRenderer::class);
        $this->directive = new IslandDirective($this->renderer);
    }

    public function testIsRegisteredUnderTheDirectiveName(): void
    {
        $this->assertSame('island', $this->directive->getName());
    }

    public function testTakesTheComponentFromThePositionalValue(): void
    {
        $this->renderer->expects($this->once())
            ->method('render')
            ->with(self::COMPONENT, null, 'eager')
            ->willReturn('<div data-mage-island></div>');

        $this->assertSame(
            '<div data-mage-island></div>',
            $this->directive->process(self::COMPONENT, ['strategy' => 'eager'], null)
        );
    }

    public function testTakesTheComponentFromTheNamedParameter(): void
    {
        $this->renderer->expects($this->once())
            ->method('render')
            ->with(self::COMPONENT, null, '');

        $this->directive->process(null, ['component' => self::COMPONENT], null);
    }

    public function testThePositionalValueWinsOverTheParameter(): void
    {
        $this->renderer->expects($this->once())
            ->method('render')
            ->with(self::COMPONENT, null, '');

        $this->directive->process(self::COMPONENT, ['component' => 'Vendor_Module::other/Thing'], null);
    }

    public function testPassesTheBodyThroughAsProps(): void
    {
        $this->renderer->expects($this->once())
            ->method('render')
            ->with(self::COMPONENT, '{"limit": 8}', '');

        $this->directive->process(self::COMPONENT, [], '{"limit": 8}');
    }

    /**
     * @dataProvider missingComponents
     */
    public function testRendersNothingWithoutAComponent(mixed $value, array $parameters): void
    {
        $this->renderer->expects($this->never())->method('render');

        $this->assertSame('', $this->directive->process($value, $parameters, null));
    }

    /**
     * @return array<string, array{mixed, array<string, mixed>}>
     */
    public static function missingComponents(): array
    {
        return [
            'nothing at all' => [null, []],
            'empty value' => ['', []],
            'empty parameter' => [null, ['component' => '']],
            'parameter is not a string' => [null, ['component' => ['Vendor_Module::x']]],
        ];
    }

    public function testAppliesNoOutputFilterToTheMarker(): void
    {
        $this->assertNull($this->directive->getDefaultFilters());
    }
}
