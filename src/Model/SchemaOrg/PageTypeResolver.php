<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Model\SchemaOrg;

use MageObsidian\ModernFrontend\Model\SchemaOrg\Builder\WebPageBuilder;

class PageTypeResolver
{
    public function __construct(
        private readonly array $map = [],
        private readonly string $default = WebPageBuilder::DEFAULT_TYPE
    ) {
    }

    public function resolve(string $fullActionName): string
    {
        $handle = trim($fullActionName);
        if ($handle === '') {
            return $this->default;
        }

        $type = $this->map[$handle] ?? null;
        if (is_string($type) && $type !== '') {
            return $type;
        }

        $route = strstr($handle, '_', true);
        $type = $route !== false ? ($this->map[$route . '_*'] ?? null) : null;

        return is_string($type) && $type !== '' ? $type : $this->default;
    }
}
