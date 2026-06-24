<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Dev;

/**
 * Pure advisory copy for `deploy:mode:set` transitions.
 *
 * The framework already enforces the hard rules elsewhere (HMR is forced off in
 * production by ConfigProvider; the Vite build runs during the production static
 * deploy via DeployViteContentPlugin). This only turns a mode switch into
 * actionable guidance so the developer knows what the modern frontend expects in
 * each mode. Kept Magento-free so it is unit testable in isolation.
 */
final class ModeAdvisor
{
    public const string MODE_DEVELOPER = 'developer';
    public const string MODE_DEFAULT = 'default';
    public const string MODE_PRODUCTION = 'production';

    /**
     * Advisory lines for a target mode. Empty for unknown modes.
     *
     * @return string[]
     */
    public static function messagesForMode(string $mode): array
    {
        return match ($mode) {
            self::MODE_DEVELOPER => [
                'MageObsidian: developer mode — HMR is available.',
                'Go live in one shot: bin/magento mage-obsidian:frontend:dev --up',
                'Diagnose anytime: bin/magento mage-obsidian:frontend:doctor',
            ],
            self::MODE_DEFAULT => [
                'MageObsidian: default mode serves built static assets; the Vite dev server is not used.',
                'Ensure static content is deployed (the Vite build runs during setup:static-content:deploy).',
                'Back to built assets in one shot: bin/magento mage-obsidian:frontend:dev --down',
            ],
            self::MODE_PRODUCTION => [
                'MageObsidian: production mode regenerated static content, including the Vite bundle.',
                'HMR is forced off in production.',
            ],
            default => [],
        };
    }
}
