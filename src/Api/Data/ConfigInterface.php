<?php
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Api\Data;

interface ConfigInterface
{
    /**
     * Version of the generated frontend contract (mage_obsidian_frontend_modules.json/.php).
     *
     * Bump on any breaking change to the contract shape. The JS engine pins the
     * version it understands and refuses a mismatching contract, so PHP and JS
     * stay in lockstep. See js-package-utils contractValidator.js.
     */
    public const string SCHEMA_VERSION = '1.0.0';
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
    public const string MODULE_CONFIG_FILE = 'module.config.ts';
    public const string THEME_CONFIG_FILE = 'theme.config.js';
    public const string THEME_CSS_SOURCE_FILE = 'theme.source.css';
    public const string THEME_FILES_PATH = 'Theme';
    public const string LIB_PATH = 'lib';

    /**
     * Where CMS content is exported so Tailwind can scan it, relative to `var/`.
     *
     * Not part of the generated contract — like GENERATED_PATH, it is a fixed
     * layout both sides agree on, mirrored by CMS_CONTENT_DIR in the engine's
     * config/default.ts. Nothing configures it, so putting it on the wire would
     * only add a schema version to keep in step.
     */
    public const string CMS_CONTENT_PATH = 'mage-obsidian/cms';
    public const string CMS_CANDIDATES_FILE = 'candidates.json';

    /**
     * The class list a build covered, written into its own output by the engine
     * so it travels with whatever gets deployed. Mirrored by CMS_BASELINE_FILE
     * in the engine's config/default.ts.
     */
    public const string CMS_BASELINE_FILE = 'cms-candidates.json';
}
