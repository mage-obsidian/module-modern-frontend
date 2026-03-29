<?php
declare(strict_types=1);

/**
 * Standalone unit bootstrap.
 *
 * These tests exercise pure logic only, so they must run without a Magento
 * install. When the Composer autoloader is present (module installed inside a
 * Magento root) it is used and takes precedence; otherwise a PSR-4 fallback
 * loads this module's sources directly from `src/`. Loading a class never
 * triggers loading of its type-hinted dependencies, so the pure static methods
 * remain callable even when the Magento framework is absent (CI). Tests that
 * mock Magento framework types are excluded from the standalone suite (see
 * phpunit.ci.xml).
 */

$autoloadCandidates = [
    __DIR__ . '/../../../../../autoload.php',           // vendor/mage-obsidian/<module>/src/Test/Unit -> vendor/autoload.php
    __DIR__ . '/../../../vendor/autoload.php',           // standalone repo with its own vendor/
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

// PSR-4 fallback for the module namespace. Registered after Composer's
// autoloader so it only resolves classes Composer can't (i.e. standalone runs).
spl_autoload_register(static function (string $class): void {
    $prefix = 'MageObsidian\\ModernFrontend\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../../' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
