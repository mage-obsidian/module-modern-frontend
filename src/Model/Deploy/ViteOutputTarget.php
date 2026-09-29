<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Model\Deploy;

/**
 * One theme's Vite output for one locale, and the files of it that the deploy
 * did not leave in the state the build produced.
 */
class ViteOutputTarget
{
    /**
     * @param string[] $files paths relative to generated/
     */
    public function __construct(
        public readonly string $theme,
        public readonly string $locale,
        public readonly string $sourceDirectory,
        public readonly string $targetDirectory,
        public readonly array $files
    ) {
    }

    public function label(): string
    {
        return $this->theme . '@' . $this->locale;
    }
}
