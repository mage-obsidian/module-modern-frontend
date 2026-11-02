<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service;

use InvalidArgumentException;

class PageConfigRenderer
{
    public const string ATTRIBUTE = 'data-mage-obsidian-config';

    public const string I18N = 'i18n';

    public const string ICONS = 'icons';

    public const string PREPAINT = 'prepaint';

    public const string SECTION_PREFETCH = 'section-prefetch-config';

    public const string SECTIONS = 'sections';

    public const string UX = 'ux';

    private const int JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR;

    private const string NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function render(string $name, array $config): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid page config name.', $name));
        }

        return sprintf(
            '<script type="application/json" %s="%s">%s</script>',
            self::ATTRIBUTE,
            $name,
            json_encode($config, self::JSON_FLAGS)
        );
    }
}
