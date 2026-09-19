<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Test\Unit\Plugin\Deploy\Service;

use Magento\Deploy\Console\DeployStaticOptions;
use Magento\Deploy\Service\DeployStaticContent;
use Magento\Framework\Exception\LocalizedException;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputPublisher;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputTarget;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputVerifier;
use MageObsidian\ModernFrontend\Plugin\Deploy\Service\VerifyViteContentPlugin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class VerifyViteContentPluginTest extends TestCase
{
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();
    }

    protected function tearDown(): void
    {
        putenv('MAGE_OBSIDIAN_STRICT_DEPLOY');
    }

    public function testSaysNothingAboutACompleteDeploy(): void
    {
        $plugin = $this->plugin(outdated: []);

        $plugin->afterDeploy($this->subject(), null, $this->options());

        $this->assertSame('', $this->output->fetch());
    }

    /**
     * The quiet failure: the deploy skipped a file that already existed, so the
     * storefront keeps serving the previous build. Nothing 404s, so reporting is
     * not enough — the file has to be put where the deploy should have put it.
     */
    public function testPublishesWhatTheDeployLeftOutOfDate(): void
    {
        $target = $this->target(['css/style.css', 'lib/vue.js']);

        $publisher = $this->createMock(ViteOutputPublisher::class);
        $publisher->expects($this->once())->method('publish')->with($target)->willReturn([]);

        $this->pluginWith($this->verifier([$target->label() => $target]), $publisher)
            ->afterDeploy($this->subject(), null, $this->options());

        $this->assertStringContainsString('published 2 Vite file(s)', $this->output->fetch());
    }

    /**
     * The other failure: a deploy whose workers died exits 0, so the only way to
     * learn that a storefront is being served without its JavaScript is to check
     * the result and say so.
     */
    public function testWarnsAboutWhatItCouldNotPublishEither(): void
    {
        $target = $this->target(['lib/vue.js', 'MageObsidian_Storefront/js/nav.js']);

        $plugin = $this->pluginWith(
            $this->verifier([$target->label() => $target]),
            $this->failingPublisher(['lib/vue.js'])
        );

        $plugin->afterDeploy($this->subject(), null, $this->options());

        $written = $this->output->fetch();
        $this->assertStringContainsString('MageObsidian/default@en_US', $written);
        $this->assertStringContainsString('lib/vue.js', $written);
    }

    // What was repaired is not what failed; a partial repair must not read as a
    // total one, nor the other way round.
    public function testCountsOnlyTheFilesItActuallyPublished(): void
    {
        $target = $this->target(['a.js', 'b.js', 'c.js']);

        $plugin = $this->pluginWith(
            $this->verifier([$target->label() => $target]),
            $this->failingPublisher(['c.js'])
        );

        $plugin->afterDeploy($this->subject(), null, $this->options());

        $this->assertStringContainsString('published 2 Vite file(s)', $this->output->fetch());
    }

    // An incomplete bundle is worth reporting, but not worth undoing a deploy
    // that Magento itself considers finished.
    public function testLetsTheDeployFinishRegardless(): void
    {
        $target = $this->target(['lib/vue.js']);
        $plugin = $this->pluginWith(
            $this->verifier([$target->label() => $target]),
            $this->failingPublisher(['lib/vue.js'])
        );

        $this->assertSame('deployed', $plugin->afterDeploy($this->subject(), 'deployed', $this->options()));
    }

    // Nothing was built, so there is nothing to verify and nothing to report.
    public function testSkipsWhenJavascriptWasExcluded(): void
    {
        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->expects($this->never())->method('findOutdated');

        $options = $this->options();
        $options[DeployStaticOptions::NO_JAVASCRIPT] = true;

        $this->pluginWith($verifier)->afterDeploy($this->subject(), null, $options);
    }

    public function testSkipsWhenTheFrontendAreaIsNotBeingDeployed(): void
    {
        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->expects($this->never())->method('findOutdated');

        $options = $this->options();
        $options[DeployStaticOptions::AREA] = ['adminhtml'];

        $this->pluginWith($verifier)->afterDeploy($this->subject(), null, $options);
    }

    /**
     * The options carry sentinels and exclusion rules that decide what was
     * deployed at all, so the verifier gets them whole rather than pre-digested.
     */
    public function testHandsTheDeployOptionsToTheVerifier(): void
    {
        $options = $this->options();

        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->expects($this->once())->method('findOutdated')->with($options)->willReturn([]);

        $this->pluginWith($verifier)->afterDeploy($this->subject(), null, $options);
    }

    public function testWarnsAboutAThemeThatWasNeverBuilt(): void
    {
        $plugin = $this->pluginWithUnbuilt(unbuilt: ['MageObsidian/default'], outdated: []);

        $plugin->afterDeploy($this->subject(), null, $this->options());

        $this->assertStringContainsString('MageObsidian/default', $this->output->fetch());
    }

    public function testStrictModeFailsTheDeployForAThemeThatWasNeverBuilt(): void
    {
        putenv('MAGE_OBSIDIAN_STRICT_DEPLOY=1');
        $plugin = $this->pluginWithUnbuilt(unbuilt: ['MageObsidian/default'], outdated: []);

        $this->expectException(LocalizedException::class);

        $plugin->afterDeploy($this->subject(), null, $this->options());
    }

    public function testStrictModeFailsTheDeployWhenFilesCouldNotBePublished(): void
    {
        putenv('MAGE_OBSIDIAN_STRICT_DEPLOY=1');
        $target = $this->target(['lib/vue.js']);
        $plugin = $this->pluginWithUnbuilt(
            unbuilt: [],
            outdated: [$target->label() => $target],
            failed: ['lib/vue.js']
        );

        $this->expectException(LocalizedException::class);

        $plugin->afterDeploy($this->subject(), null, $this->options());
    }

    public function testWithoutStrictModeUnpublishedFilesOnlyWarn(): void
    {
        $target = $this->target(['lib/vue.js']);
        $plugin = $this->pluginWithUnbuilt(
            unbuilt: [],
            outdated: [$target->label() => $target],
            failed: ['lib/vue.js']
        );

        $plugin->afterDeploy($this->subject(), null, $this->options());

        $this->assertStringContainsString('did not publish the whole Vite build', $this->output->fetch());
    }

    private function pluginWithUnbuilt(array $unbuilt, array $outdated, array $failed = []): VerifyViteContentPlugin
    {
        $verifier = $this->createStub(ViteOutputVerifier::class);
        $verifier->method('findUnbuilt')->willReturn($unbuilt);
        $verifier->method('findOutdated')->willReturn($outdated);

        return $this->pluginWith($verifier, $this->failingPublisher($failed));
    }

    /**
     * @param string[] $files
     */
    private function target(array $files): ViteOutputTarget
    {
        return new ViteOutputTarget(
            'MageObsidian/default',
            'en_US',
            '/var/www/html/vendor/mage-obsidian/theme-default/web/generated',
            '/var/www/html/pub/static/frontend/MageObsidian/default/en_US/generated',
            $files
        );
    }

    /**
     * @param array<string, ViteOutputTarget> $outdated
     */
    private function verifier(array $outdated): ViteOutputVerifier
    {
        $verifier = $this->createStub(ViteOutputVerifier::class);
        $verifier->method('findOutdated')->willReturn($outdated);

        return $verifier;
    }

    /**
     * @param string[] $failing
     */
    private function failingPublisher(array $failing): ViteOutputPublisher
    {
        $publisher = $this->createStub(ViteOutputPublisher::class);
        $publisher->method('publish')->willReturn($failing);

        return $publisher;
    }

    /**
     * @param array<string, ViteOutputTarget> $outdated
     */
    private function plugin(array $outdated): VerifyViteContentPlugin
    {
        return $this->pluginWith($this->verifier($outdated));
    }

    private function pluginWith(
        ViteOutputVerifier $verifier,
        ?ViteOutputPublisher $publisher = null
    ): VerifyViteContentPlugin {
        return new VerifyViteContentPlugin(
            $verifier,
            $publisher ?? $this->createStub(ViteOutputPublisher::class),
            $this->output
        );
    }

    private function subject(): DeployStaticContent
    {
        return $this->createStub(DeployStaticContent::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            DeployStaticOptions::NO_JAVASCRIPT => false,
            DeployStaticOptions::AREA => ['all'],
            DeployStaticOptions::EXCLUDE_AREA => [],
            DeployStaticOptions::LANGUAGE => ['all'],
        ];
    }
}
