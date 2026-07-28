<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Cms;

use MageObsidian\ModernFrontend\Service\Cms\IslandRegistry;
use MageObsidian\ModernFrontend\Service\Cms\IslandRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Content is authored by people who will not read a stack trace, so every bad
 * reference has to degrade to "nothing rendered, reason logged" instead of a
 * fatal — while a good one still reaches ViteResolver with the merged props.
 */
class IslandRendererTest extends TestCase
{
    private const string COMPONENT = 'Vendor_Module::catalog/ProductCarousel';

    private ViteResolver&MockObject $viteResolver;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->viteResolver = $this->createMock(ViteResolver::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function renderer(array $islands = null): IslandRenderer
    {
        $islands ??= [
            'carousel' => ['component' => self::COMPONENT, 'props' => ['limit' => 4, 'title' => 'Featured']],
        ];

        return new IslandRenderer(new IslandRegistry($islands), $this->viteResolver, $this->logger);
    }

    public function testRendersARegisteredIslandWithItsDefaults(): void
    {
        $this->viteResolver->expects($this->once())
            ->method('renderVueComponent')
            ->with(self::COMPONENT, ['limit' => 4, 'title' => 'Featured'], false)
            ->willReturn('<div data-mage-island></div>');
        $this->logger->expects($this->never())->method('warning');

        $this->assertSame('<div data-mage-island></div>', $this->renderer()->render(self::COMPONENT));
    }

    public function testAuthoredPropsOverrideTheDefaultsOneKeyAtATime(): void
    {
        $this->viteResolver->expects($this->once())
            ->method('renderVueComponent')
            ->with(self::COMPONENT, ['limit' => 8, 'title' => 'Featured'], false);

        $this->renderer()->render(self::COMPONENT, '{"limit": 8}');
    }

    public function testTheEagerStrategyIsTheOnlyOneThatMountsImmediately(): void
    {
        $this->viteResolver->expects($this->exactly(3))
            ->method('renderVueComponent')
            ->willReturnCallback(function (string $name, array $props, bool $eager) {
                $this->assertSame($eager, $name === 'eager-case');

                return '';
            });

        $renderer = $this->renderer([
            'a' => ['component' => 'eager-case'],
            'b' => ['component' => 'visible-case'],
            'c' => ['component' => 'default-case'],
        ]);
        $renderer->render('eager-case', null, IslandRenderer::STRATEGY_EAGER);
        $renderer->render('visible-case', null, IslandRenderer::STRATEGY_VISIBLE);
        $renderer->render('default-case');
    }

    public function testAnUnregisteredComponentRendersNothingAndSaysWhy(): void
    {
        $this->viteResolver->expects($this->never())->method('renderVueComponent');
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Vendor_Module::checkout/PaymentStep'));

        $this->assertSame('', $this->renderer()->render('Vendor_Module::checkout/PaymentStep'));
    }

    public function testMalformedPropsFallBackToTheDefaults(): void
    {
        $this->viteResolver->expects($this->once())
            ->method('renderVueComponent')
            ->with(self::COMPONENT, ['limit' => 4, 'title' => 'Featured'], false);
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('not valid JSON'));

        $this->renderer()->render(self::COMPONENT, '{limit: 8}');
    }

    public function testEmptyPropsAreNotTreatedAsMalformed(): void
    {
        $this->viteResolver->expects($this->exactly(2))
            ->method('renderVueComponent')
            ->with(self::COMPONENT, ['limit' => 4, 'title' => 'Featured'], false);
        $this->logger->expects($this->never())->method('warning');

        $this->renderer()->render(self::COMPONENT, '');
        $this->renderer()->render(self::COMPONENT, "\n   \n");
    }

    public function testAcceptsThePropsAWidgetStoredEscaped(): void
    {
        $this->viteResolver->expects($this->once())
            ->method('renderVueComponent')
            ->with(self::COMPONENT, ['limit' => 8, 'title' => 'Featured'], false);
        $this->logger->expects($this->never())->method('warning');

        $this->renderer()->render(self::COMPONENT, '{&quot;limit&quot;: 8}');
    }

    public function testAnEntityInsideValidJsonSurvivesUnescaping(): void
    {
        $this->viteResolver->expects($this->once())
            ->method('renderVueComponent')
            ->with(self::COMPONENT, ['limit' => 4, 'title' => 'Tom &amp; Jerry'], false);

        $this->renderer()->render(self::COMPONENT, '{"title": "Tom &amp; Jerry"}');
    }

    public function testAlreadyDecodedPropsAreUsedAsGiven(): void
    {
        $this->viteResolver->expects($this->once())
            ->method('renderVueComponent')
            ->with(self::COMPONENT, ['limit' => 2, 'title' => 'Featured'], false);

        $this->renderer()->render(self::COMPONENT, ['limit' => 2]);
    }
}
