<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Test\Integration\Service;

use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Service\ConfigManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: exercises the real DI wiring and the real module/theme
 * loaders (against the installed codebase), which the mocked unit test cannot.
 * Runs under Magento's integration TestFramework (dev/tests/integration).
 */
class ConfigManagerTest extends TestCase
{
    private ConfigManagerInterface $configManager;

    protected function setUp(): void
    {
        $this->configManager = Bootstrap::getObjectManager()->get(ConfigManagerInterface::class);
    }

    public function testApiInterfaceResolvesToConcreteImplementation(): void
    {
        // Proves the <preference> added in Fase 6 wires end-to-end.
        $this->assertInstanceOf(ConfigManager::class, $this->configManager);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testGenerateProducesContractForThisModule(): void
    {
        $config = $this->configManager->generate();

        $this->assertSame('1.0.0', $config['schema_version']);
        $this->assertIsArray($config['modules']);
        $this->assertArrayHasKey(
            'MageObsidian_ModernFrontend',
            $config['modules'],
            'The framework module ships a compatibility descriptor and must be in the contract.'
        );
        $this->assertTrue($this->configManager->isModuleEnabled('MageObsidian_ModernFrontend'));
        $this->assertFalse($this->configManager->isModuleEnabled('Nonexistent_Module'));
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testDetectDriftIsCleanRightAfterGenerate(): void
    {
        $this->configManager->generate();

        $drift = $this->configManager->detectDrift();

        $this->assertSame([], $drift['modules']['added']);
        $this->assertSame([], $drift['modules']['removed']);
        $this->assertSame([], $drift['themes']['added']);
        $this->assertSame([], $drift['themes']['removed']);
    }
}
