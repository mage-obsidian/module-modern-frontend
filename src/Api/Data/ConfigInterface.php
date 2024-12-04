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
    const string VUE_COMPONENTS_PATH = 'components';
    const string JS_PATH = 'js';
    const array FOLDERS_TO_WATCH = [
        self::VUE_COMPONENTS_PATH,
        self::JS_PATH
    ];
    const array ALLOWED_EXTENSIONS = [
        'js',
        'vue',
        'cjs',
        'ts'
    ];
    const string GENERATED_PATH = 'generated';
    const string VITE_GENERATED_PATH = 'vite_generated';
    const string MODULE_CSS_EXTEND_FILE = 'module.extend.css';
    const string MODULE_CONFIG_FILE = 'module.config.cjs';
    const string THEME_CONFIG_FILE = 'theme.config.cjs';
    const string THEME_CSS_SOURCE_FILE = 'theme.source.css';
    const string THEME_FILES_PATH = 'Theme';
    const string LIB_PATH = 'lib';
}
