<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Cms;

use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use Psr\Log\LoggerInterface;

/**
 * Turns an island reference written in content into an island marker.
 *
 * Shared by the widget and the `{{island}}` directive so both answer the same
 * way to an unregistered component, malformed props or a missing strategy —
 * content is authored by people who are not looking at a stack trace, so a bad
 * reference renders nothing and says why in the log instead of breaking the page.
 */
class IslandRenderer
{
    public const string STRATEGY_EAGER = 'eager';
    public const string STRATEGY_VISIBLE = 'visible';

    public function __construct(
        private readonly IslandRegistry $registry,
        private readonly ViteResolver $viteResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string $component Island name, `Vendor_Module::path`.
     * @param string|array|null $props JSON as authored, or an already decoded array.
     * @param string $strategy `eager` to mount immediately, anything else to wait for the viewport.
     *
     * @return string The island marker, or an empty string when the reference is unusable.
     */
    public function render(string $component, string|array|null $props = null, string $strategy = ''): string
    {
        $island = $this->registry->get($component);
        if ($island === null) {
            $this->logger->warning(sprintf(
                'MageObsidian: content references the island "%s", which is not registered for CMS use. '
                . 'Add it to MageObsidian\ModernFrontend\Service\Cms\IslandRegistry in di.xml.',
                $component
            ));

            return '';
        }

        return $this->viteResolver->renderVueComponent(
            $component,
            array_replace($island['props'], $this->decodeProps($props, $component)),
            $strategy === self::STRATEGY_EAGER
        );
    }

    /**
     * @param string|array|null $props
     *
     * @return array<string, mixed>
     */
    private function decodeProps(string|array|null $props, string $component): array
    {
        if (is_array($props)) {
            return $props;
        }
        $props = trim((string)$props);
        if ($props === '') {
            return [];
        }

        $decoded = json_decode($props, true);
        // A widget stores its parameters escaped, so the JSON an author typed
        // into the admin field arrives as `{&quot;a&quot;:1}`. Unescaping is the
        // retry rather than the first attempt: a string value that legitimately
        // contains `&quot;` must survive when the JSON already parses.
        if (!is_array($decoded)) {
            $decoded = json_decode(html_entity_decode($props, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), true);
        }
        if (!is_array($decoded)) {
            $this->logger->warning(sprintf(
                'MageObsidian: the props given to the island "%s" are not valid JSON (%s); '
                . 'falling back to the registered defaults.',
                $component,
                json_last_error_msg()
            ));

            return [];
        }

        return $decoded;
    }
}
