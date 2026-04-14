<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg\Builder;

/**
 * Builds a `BreadcrumbList` schema.org node from an ordered list of crumbs.
 *
 * Pure: the caller resolves the crumbs (typically from Magento's breadcrumb
 * path) into `['name' => ..., 'url' => ...]` entries. Nameless crumbs are
 * dropped and `position` is renumbered over the surviving ones so the list is
 * always 1..n contiguous. The trailing crumb (current page) may omit its URL.
 */
class BreadcrumbListBuilder
{
    /**
     * @param list<array{name?:string|null, url?:string|null}> $crumbs Ordered crumbs.
     *
     * @return array<string,mixed> The node, or [] when no valid crumb remains.
     */
    public function build(array $crumbs): array
    {
        $elements = [];
        $position = 1;

        foreach ($crumbs as $crumb) {
            $name = (string)($crumb['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $element = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $name,
            ];

            $url = $crumb['url'] ?? null;
            if ($url !== null && $url !== '') {
                $element['item'] = $url;
            }

            $elements[] = $element;
            $position++;
        }

        if ($elements === []) {
            return [];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }
}
