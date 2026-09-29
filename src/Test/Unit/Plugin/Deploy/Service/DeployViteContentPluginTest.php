<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Deploy\Service;

use Magento\Deploy\Console\DeployStaticOptions;
use Magento\Deploy\Package\LocaleResolver;
use Magento\Deploy\Package\PackageFactory;
use Magento\Deploy\Service\DeployStaticContent;
use Magento\Framework\App\Filesystem\DirectoryList;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Model\Deploy\DeployTargets;
use MageObsidian\ModernFrontend\Model\Deploy\ViteBuildPreflight;
use MageObsidian\ModernFrontend\Model\Deploy\ViteBuildRunner;
use MageObsidian\ModernFrontend\Plugin\Deploy\Service\DeployViteContentPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class DeployViteContentPluginTest extends TestCase
{
    private const array THEMES = [
        'Acme/base' => ['src' => '/code/app/design/frontend/Acme/base', 'parent' => null],
        'Acme/shop' => ['src' => '/code/app/design/frontend/Acme/shop', 'parent' => 'Acme/base'],
    ];

    private ConfigManagerInterface&MockObject $configManager;
    private ViteBuildRunner&MockObject $runner;
    private ViteBuildPreflight&MockObject $preflight;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        putenv(DeployViteContentPlugin::SKIP_BUILD_ENV_VAR);
        $this->configManager = $this->createMock(ConfigManagerInterface::class);
        $this->configManager->method('generate')->willReturn(['themes' => self::THEMES]);
        $this->runner = $this->createMock(ViteBuildRunner::class);
        $this->preflight = $this->createMock(ViteBuildPreflight::class);
        $this->output = new BufferedOutput();
    }

    protected function tearDown(): void
    {
        putenv(DeployViteContentPlugin::SKIP_BUILD_ENV_VAR);
    }

    public function testRegeneratesTheContractBeforeASingleBuildOfEveryTheme(): void
    {
        $this->configManager->expects($this->once())->method('generate');
        $this->runner->expects($this->once())->method('build')->with(null);

        $this->deploy([]);
    }

    public function testNoJavascriptSkipsTheContractAndTheBuild(): void
    {
        $this->configManager->expects($this->never())->method('generate');
        $this->runner->expects($this->never())->method('build');

        $this->deploy([DeployStaticOptions::NO_JAVASCRIPT => true]);
    }

    public function testSkipVariableRegeneratesTheContractWithoutBuilding(): void
    {
        putenv(DeployViteContentPlugin::SKIP_BUILD_ENV_VAR . '=1');
        $this->configManager->expects($this->once())->method('generate');
        $this->runner->expects($this->never())->method('build');
        $this->preflight->expects($this->never())->method('assertWritable');

        $this->deploy([]);

        $this->assertStringContainsString(DeployViteContentPlugin::SKIP_BUILD_ENV_VAR, $this->output->fetch());
    }

    public function testAFalseSkipVariableStillBuilds(): void
    {
        putenv(DeployViteContentPlugin::SKIP_BUILD_ENV_VAR . '=0');
        $this->runner->expects($this->once())->method('build');

        $this->deploy([]);
    }

    public function testAnExcludedThemeLeavesTheOthersBuiltOneByOne(): void
    {
        $this->runner->expects($this->once())->method('build')->with('Acme/base');

        $this->deploy([DeployStaticOptions::EXCLUDE_THEME => ['Acme/shop']]);
    }

    public function testExcludeNoneIsASingleBuildOfEveryTheme(): void
    {
        $this->runner->expects($this->once())->method('build')->with(null);

        $this->deploy([DeployStaticOptions::EXCLUDE_THEME => ['none']]);
    }

    public function testExclusionWinsOverInclusionAsInMagento(): void
    {
        $this->runner->expects($this->once())->method('build')->with('Acme/base');

        $this->deploy([
            DeployStaticOptions::THEME => ['Acme/shop'],
            DeployStaticOptions::EXCLUDE_THEME => ['Acme/shop'],
        ]);
    }

    public function testExcludingEveryThemeBuildsNothingAndSaysSo(): void
    {
        $this->runner->expects($this->never())->method('build');
        $this->preflight->expects($this->never())->method('assertWritable');

        $this->deploy([DeployStaticOptions::EXCLUDE_THEME => ['Acme/base', 'Acme/shop']]);

        $this->assertStringContainsString('nothing to build', $this->output->fetch());
    }

    public function testChecksWritabilityOfTheHarnessAndEachBuiltThemeOutput(): void
    {
        $this->preflight->expects($this->once())->method('assertWritable')->with(
            '/code/vite',
            ['/code/app/design/frontend/Acme/base/web/generated']
        );

        $this->deploy([DeployStaticOptions::EXCLUDE_THEME => ['Acme/shop']]);
    }

    public function testIsBuildSkippedReadsBooleanValues(): void
    {
        $this->assertTrue(DeployViteContentPlugin::isBuildSkipped('1'));
        $this->assertTrue(DeployViteContentPlugin::isBuildSkipped('true'));
        $this->assertFalse(DeployViteContentPlugin::isBuildSkipped('0'));
        $this->assertFalse(DeployViteContentPlugin::isBuildSkipped(''));
        $this->assertFalse(DeployViteContentPlugin::isBuildSkipped(false));
    }

    private function deploy(array $overrides): void
    {
        $options = $overrides + [
            DeployStaticOptions::NO_JAVASCRIPT => false,
            DeployStaticOptions::AREA => ['all'],
            DeployStaticOptions::EXCLUDE_AREA => ['none'],
            DeployStaticOptions::THEME => ['all'],
            DeployStaticOptions::EXCLUDE_THEME => ['none'],
        ];
        $directoryList = $this->createStub(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn('/code');

        $plugin = new DeployViteContentPlugin(
            $this->configManager,
            new DeployTargets($this->createStub(LocaleResolver::class), $this->createStub(PackageFactory::class)),
            $this->runner,
            $this->preflight,
            $directoryList,
            $this->output
        );

        $plugin->beforeDeploy($this->createStub(DeployStaticContent::class), $options);
    }
}
