<?php
declare(strict_types=1);

/**
 * Standalone unit bootstrap.
 *
 * These tests exercise pure logic only, so they must run without a Magento
 * install. When the Composer autoloader is present (module installed inside a
 * Magento root) it is used; otherwise the sources under test are required
 * directly. Loading a class never triggers loading of its type-hinted
 * dependencies, so the pure static methods remain callable either way.
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

if (!class_exists(\MageObsidian\ModernFrontend\Plugin\Deploy\Service\DeployViteContentPlugin::class, false)) {
    require __DIR__ . '/../../Plugin/Deploy/Service/DeployViteContentPlugin.php';
}
