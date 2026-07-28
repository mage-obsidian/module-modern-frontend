<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Cms;

/**
 * The islands a module allows to be placed from the CMS.
 *
 * Most components are not candidates: a product form needs a product in the
 * registry, a cart counter belongs to the header. Offering every built
 * component would hand an author a list of things that break off their own
 * page, so exposure is a decision a module makes explicitly:
 *
 *     <type name="MageObsidian\ModernFrontend\Service\Cms\IslandRegistry">
 *         <arguments>
 *             <argument name="islands" xsi:type="array">
 *                 <item name="product_carousel" xsi:type="array">
 *                     <item name="component" xsi:type="string">Vendor_Module::catalog/ProductCarousel</item>
 *                     <item name="label" xsi:type="string" translate="true">Product carousel</item>
 *                     <item name="props" xsi:type="array">
 *                         <item name="limit" xsi:type="number">4</item>
 *                     </item>
 *                 </item>
 *             </argument>
 *         </arguments>
 *     </type>
 *
 * The array merges across modules, so a module contributes its own without
 * touching anyone else's. Both the widget and the `{{island}}` directive read
 * this, so there is one answer to "is this allowed in content".
 */
class IslandRegistry
{
    /** @var array<string, array{component: string, label: string, description: string, props: array}>|null */
    private ?array $byComponent = null;

    /**
     * @param array<string, mixed> $islands
     */
    public function __construct(private readonly array $islands = [])
    {
    }

    /**
     * Registered islands, indexed by component name.
     *
     * @return array<string, array{component: string, label: string, description: string, props: array}>
     */
    public function getAll(): array
    {
        if ($this->byComponent !== null) {
            return $this->byComponent;
        }

        $registered = [];
        foreach ($this->islands as $key => $island) {
            if (!is_array($island)) {
                continue;
            }
            $component = $island['component'] ?? null;
            if (!is_string($component) || $component === '') {
                continue;
            }
            $label = $island['label'] ?? null;
            $description = $island['description'] ?? null;
            $props = $island['props'] ?? [];

            $registered[$component] = [
                'component' => $component,
                'label' => is_scalar($label) && (string)$label !== '' ? (string)$label : (string)$key,
                'description' => is_scalar($description) ? (string)$description : '',
                'props' => is_array($props) ? $props : [],
            ];
        }

        return $this->byComponent = $registered;
    }

    public function has(string $component): bool
    {
        return isset($this->getAll()[$component]);
    }

    /**
     * @return array{component: string, label: string, description: string, props: array}|null
     */
    public function get(string $component): ?array
    {
        return $this->getAll()[$component] ?? null;
    }
}
