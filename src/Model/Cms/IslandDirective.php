<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\Cms;

use Magento\Framework\Filter\SimpleDirective\ProcessorInterface;
use MageObsidian\ModernFrontend\Service\Cms\IslandRenderer;

/**
 * `{{island}}` — the hand-written path to a Vue island inside content.
 *
 *     {{island "Vendor_Module::catalog/ProductCarousel" strategy="eager"}}
 *     {{island component="Vendor_Module::catalog/ProductCarousel"}}{"limit": 8}{{/island}}
 *
 * Props go in the body, not in a parameter: the parameter tokenizer reads
 * `key="value"` pairs, so a JSON object's own double quotes would end the value
 * halfway through.
 *
 * Registered in the SimpleDirective pool rather than as a directive processor of
 * its own. A second processor would be a second match of the same text: the
 * legacy processor's pattern matches any `{{name}}`, and since it hands an
 * unknown directive back unchanged, its result would overwrite this one — the
 * marker would be replaced by the literal directive again.
 */
class IslandDirective implements ProcessorInterface
{
    public const string NAME = 'island';

    public function __construct(private readonly IslandRenderer $renderer)
    {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @param mixed $value The component name when written as `{{island "Vendor_Module::path"}}`.
     * @param string[] $parameters
     * @param string|null $html Props, as a JSON object.
     *
     * @return string
     */
    public function process($value, array $parameters, ?string $html): string
    {
        $component = is_string($value) && $value !== '' ? $value : ($parameters['component'] ?? '');
        if (!is_string($component) || $component === '') {
            return '';
        }

        return $this->renderer->render($component, $html, (string)($parameters['strategy'] ?? ''));
    }

    /**
     * None: the directive emits the island marker, and escaping it would ship
     * the markup as text.
     *
     * @return string[]|null
     */
    public function getDefaultFilters(): ?array
    {
        return null;
    }
}
