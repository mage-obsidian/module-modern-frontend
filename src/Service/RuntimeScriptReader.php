<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Service;

use Magento\Framework\Module\Dir\Reader;

class RuntimeScriptReader
{
    private const string LEADING_LINE_COMMENTS = '~\A(?:[ \t]*//[^\n]*\n)+~';

    public function __construct(
        private readonly Reader $moduleReader
    ) {
    }

    public function read(string $moduleName, string $viewPath): string
    {
        $path = $this->moduleReader->getModuleDir('view', $moduleName) . $viewPath;
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        return (string)preg_replace(self::LEADING_LINE_COMMENTS, '', (string)file_get_contents($path));
    }
}
