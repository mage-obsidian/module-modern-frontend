<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Contract;

/**
 * Pure diff between the contract persisted on disk and the contract freshly
 * recomputed from the enabled modules/themes.
 *
 * Surfaces the case the WriterPlugin seam cannot catch: editing a module's
 * `<compatibility>` flag (or its descriptor) without a module-state change
 * leaves the contract stale until the next regeneration. Pure, so it is fully
 * unit-testable without Magento.
 */
class ContractDiff
{
    /**
     * Diff one section (modules or themes), keyed by name.
     *
     * @param array<string, mixed> $current  Entries from the on-disk contract.
     * @param array<string, mixed> $expected Entries recomputed from current state.
     *
     * @return array{added: string[], removed: string[], changed: string[]}
     */
    public static function section(array $current, array $expected): array
    {
        $added = array_values(array_diff(array_keys($expected), array_keys($current)));
        $removed = array_values(array_diff(array_keys($current), array_keys($expected)));

        $changed = [];
        foreach ($expected as $key => $value) {
            if (array_key_exists($key, $current) && $current[$key] !== $value) {
                $changed[] = $key;
            }
        }

        return ['added' => $added, 'removed' => $removed, 'changed' => $changed];
    }

    /**
     * Whether a composed drift (per-section results) carries no differences.
     *
     * @param array<string, array{added: string[], removed: string[], changed: string[]}> $drift
     */
    public static function isEmpty(array $drift): bool
    {
        foreach ($drift as $section) {
            if (($section['added'] ?? []) !== []
                || ($section['removed'] ?? []) !== []
                || ($section['changed'] ?? []) !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Human-readable one-line summary of a composed drift, e.g.
     * "modules +1 -0 ~1; themes +0 -1 ~0".
     *
     * @param array<string, array{added: string[], removed: string[], changed: string[]}> $drift
     */
    public static function summarize(array $drift): string
    {
        $parts = [];
        foreach ($drift as $name => $section) {
            $parts[] = sprintf(
                '%s +%d -%d ~%d',
                $name,
                count($section['added'] ?? []),
                count($section['removed'] ?? []),
                count($section['changed'] ?? [])
            );
        }

        return implode('; ', $parts);
    }
}
