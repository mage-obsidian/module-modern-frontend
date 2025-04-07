<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos
 */

namespace MageObsidian\ModernFrontend\Api\Data;

interface ConfigInterface
{
    public const string VUE_COMPONENTS_PATH = 'components';
    public const string JS_PATH = 'js';
    public const array FOLDERS_TO_WATCH = [
            self::VUE_COMPONENTS_PATH,
            self::JS_PATH
    ];
    public const array ALLOWED_EXTENSIONS = [
            'js',
            'vue',
            'cjs',
            'ts'
    ];
    public const string GENERATED_PATH = 'generated';
    public const string VITE_GENERATED_PATH = 'vite_generated';
    public const string MODULE_CSS_EXTEND_FILE = 'module.extend.css';
    public const string MODULE_CONFIG_FILE = 'module.config.js';
    public const string THEME_CONFIG_FILE = 'theme.config.js';
    public const string THEME_CSS_SOURCE_FILE = 'theme.source.css';
    public const string THEME_FILES_PATH = 'Theme';
    public const string LIB_PATH = 'lib';
}
