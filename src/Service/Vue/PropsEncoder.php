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
 * Encodes Vue component props for safe embedding in the island marker's
 * `data-props` HTML attribute.
 *
 * Pure (no Magento dependencies) so the escaping and error-handling rules are
 * unit-testable in isolation.
 */
class PropsEncoder
{
    /**
     * Produce valid JSON, escaped for an HTML double-quoted attribute. The
     * browser decodes the entities back to clean JSON before the island
     * bootstrap calls `JSON.parse(el.dataset.props)`.
     *
     * @param string $componentName Used only to build an actionable error message.
     * @param array $props
     *
     * @return string Attribute-safe JSON.
     * @throws InvalidArgumentException When the props are not JSON-encodable.
     */
    public static function encodeAttribute(string $componentName, array $props): string
    {
        $propsJson = json_encode($props);

        // An un-encodable value (malformed UTF-8, a resource, NAN/INF) makes
        // json_encode return false, which would otherwise emit a broken
        // attribute. Fail loudly instead.
        if ($propsJson === false || json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(sprintf(
                'Cannot render Vue island "%s": props are not JSON-encodable (%s).',
                $componentName,
                json_last_error_msg()
            ));
        }

        // Entity-escape <, >, &, " and ' so the JSON cannot break out of the
        // attribute or the surrounding markup.
        return htmlspecialchars($propsJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
