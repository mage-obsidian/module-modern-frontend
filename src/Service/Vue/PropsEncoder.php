<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Vue;

use InvalidArgumentException;

/**
 * Encodes Vue component props for safe inlining inside a
 * `<script type="module">` block.
 *
 * Pure (no Magento dependencies) so the escaping and error-handling rules are
 * unit-testable in isolation.
 */
class PropsEncoder
{
    /**
     * @param string $componentName Used only to build an actionable error message.
     * @param array $props
     *
     * @return string JSON safe to inline inside a script element.
     * @throws InvalidArgumentException When the props are not JSON-encodable.
     */
    public static function encode(string $componentName, array $props): string
    {
        // The HEX flags escape <, >, &, ' and " so the payload cannot break out
        // of the script element or an HTML attribute.
        $propsJson = json_encode($props, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        // An un-encodable value (malformed UTF-8, a resource, NAN/INF) makes
        // json_encode return false, which would otherwise inline as an empty
        // argument and yield broken JS. Fail loudly instead.
        if ($propsJson === false || json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(sprintf(
                'Cannot render Vue component "%s": props are not JSON-encodable (%s).',
                $componentName,
                json_last_error_msg()
            ));
        }

        return $propsJson;
    }
}
