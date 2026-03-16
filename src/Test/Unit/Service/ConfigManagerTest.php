<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service;

use MageObsidian\ModernFrontend\Api\ModuleListInterface;
use MageObsidian\ModernFrontend\Api\ThemeListInterface;
use MageObsidian\ModernFrontend\Service\ConfigManager;
use Magento\Framework\App\DeploymentConfig\Writer\FormatterInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Module\ModuleList as MagentoModuleList;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Mock-based unit test for the IO-heavy ConfigManager. Requires the Magento
 * autoloader (the mocked types are framework interfaces) and the bundled JSON
 * schema, so it runs inside a Magento root — not the bare standalone bootstrap.
 */
class ConfigManagerTest extends TestCase
{
    private const ROOT = '/tmp/mage-obsidian-cfgtest';
    private const JSON_TMP = self::ROOT . '/app/etc/mage_obsidian_frontend_modules.json.tmp';

    private string $schemaJson;
    /** @var array<string, string> path => written content */
    private array $writes = [];

    protected function setUp(): void
    {
        $schemaPath = __DIR__ . '/../../../etc/mage_obsidian_frontend_contract.schema.json';
        $this->schemaJson = (string)file_get_contents($schemaPath);
        $this->writes = [];
    }

    public function testGenerateProducesSchemaValidContract(): void
    {
        $manager = $this->buildManager(
            modules: ['Vendor_Mod' => ['path' => '/src/Vendor/Mod']],
            themes: ['Vendor/theme' => ['path' => '/src/theme', 'parent_code' => null]],
            allModules: ['Vendor_Mod', 'Magento_Store'],
            mode: 'developer'
        );

        // No throw means the generated payload validated against the real schema.
        $manager->generate();

        $json = json_decode($this->writes[self::JSON_TMP], true);
        $this->assertSame('1.0.0', $json['schema_version']);
        $this->assertSame('developer', $json['mode']);
        $this->assertSame(['src' => '/src/Vendor/Mod'], $json['modules']['Vendor_Mod']);
        $this->assertSame(['src' => '/src/theme', 'parent' => null], $json['themes']['Vendor/theme']);
        $this->assertSame(['Vendor_Mod', 'Magento_Store'], $json['allModules']);
    }

    public function testGenerateSerializesEmptyThemesAsJsonObject(): void
    {
        $manager = $this->buildManager(
            modules: ['Vendor_Mod' => ['path' => '/src/Vendor/Mod']],
            themes: [],
            allModules: ['Vendor_Mod'],
            mode: 'production'
        );

        $manager->generate();

        // Empty section must be {} (object), not [] (array), or the schema and
        // the JS object access break.
        $this->assertStringContainsString('"themes": {}', $this->writes[self::JSON_TMP]);
        $this->assertStringNotContainsString('"themes": []', $this->writes[self::JSON_TMP]);
    }

    public function testIsModuleAndThemeEnabledReflectGeneratedContract(): void
    {
        $manager = $this->buildManager(
            modules: ['Vendor_Mod' => ['path' => '/src/Vendor/Mod']],
            themes: ['Vendor/theme' => ['path' => '/src/theme', 'parent_code' => 'Magento/blank']],
            allModules: ['Vendor_Mod'],
            mode: 'developer'
        );
        $manager->generate(); // populates in-memory configData

        $this->assertTrue($manager->isModuleEnabled('Vendor_Mod'));
        $this->assertFalse($manager->isModuleEnabled('Other_Mod'));
        $this->assertTrue($manager->isThemeEnabled('Vendor/theme'));
        $this->assertFalse($manager->isThemeEnabled('Vendor/missing'));
    }

    public function testDetectDriftReportsModulesAddedSinceGeneration(): void
    {
        // getAllEnabled is called once by generate() and again by detectDrift();
        // returning a larger set the second time simulates a module enabled
        // after the contract was written.
        $moduleList = $this->createMock(ModuleListInterface::class);
        $moduleList->method('getAllEnabled')->willReturnOnConsecutiveCalls(
            ['Vendor_Mod' => ['path' => '/src/Vendor/Mod']],
            ['Vendor_Mod' => ['path' => '/src/Vendor/Mod'], 'Vendor_New' => ['path' => '/src/Vendor/New']]
        );
        $themeList = $this->createMock(ThemeListInterface::class);
        $themeList->method('getAllEnabled')->willReturn([]);

        $manager = $this->buildManagerWith($moduleList, $themeList, ['Vendor_Mod'], 'developer');
        $manager->generate();

        $drift = $manager->detectDrift();

        $this->assertSame(['Vendor_New'], $drift['modules']['added']);
        $this->assertSame([], $drift['modules']['removed']);
        $this->assertSame([], $drift['themes']['added']);
    }

    /**
     * @param array<string, array> $modules
     * @param array<string, array> $themes
     * @param string[] $allModules
     */
    private function buildManager(array $modules, array $themes, array $allModules, string $mode): ConfigManager
    {
        $moduleList = $this->createMock(ModuleListInterface::class);
        $moduleList->method('getAllEnabled')->willReturn($modules);
        $themeList = $this->createMock(ThemeListInterface::class);
        $themeList->method('getAllEnabled')->willReturn($themes);

        return $this->buildManagerWith($moduleList, $themeList, $allModules, $mode);
    }

    /**
     * @param string[] $allModules
     */
    private function buildManagerWith(
        ModuleListInterface $moduleList,
        ThemeListInterface $themeList,
        array $allModules,
        string $mode
    ): ConfigManager {
        $magentoModuleList = $this->createMock(MagentoModuleList::class);
        $magentoModuleList->method('getNames')->willReturn($allModules);

        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getPath')->willReturn(self::ROOT);

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('filePutContents')->willReturnCallback(function (string $path, string $data): int {
            $this->writes[$path] = $data;
            return strlen($data);
        });
        $driver->method('rename')->willReturn(true);
        $driver->method('fileGetContents')->willReturnCallback(
            fn(string $path): string => str_contains($path, 'schema') ? $this->schemaJson : ''
        );

        $formatter = $this->createMock(FormatterInterface::class);
        $formatter->method('format')->willReturn('<?php return [];');

        $state = $this->createMock(State::class);
        $state->method('getMode')->willReturn($mode);

        $moduleDirReader = $this->createMock(ModuleDirReader::class);
        $moduleDirReader->method('getModuleDir')->willReturn('/module/etc');

        return new ConfigManager(
            $moduleList,
            $themeList,
            $magentoModuleList,
            $directoryList,
            $driver,
            $formatter,
            $state,
            $moduleDirReader,
            $this->createMock(LoggerInterface::class)
        );
    }
}
