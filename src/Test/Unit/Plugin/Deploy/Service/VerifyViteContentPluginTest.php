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

    public function testSaysNothingAboutACompleteDeploy(): void
    {
        $plugin = $this->plugin(missing: []);

        $plugin->afterDeploy($this->subject(), null, $this->options());

        $this->assertSame('', $this->output->fetch());
    }

    /**
     * The whole point: a deploy whose workers died exits 0, so the only way to
     * learn that a storefront is being served without its JavaScript is to
     * check the result and say so.
     */
    public function testWarnsWhenTheBundleIsMissing(): void
    {
        $plugin = $this->plugin(missing: [
            'MageObsidian/default@en_US' => ['lib/vue.js', 'MageObsidian_Storefront/js/nav.js'],
        ]);

        $plugin->afterDeploy($this->subject(), null, $this->options());

        $this->assertStringContainsString('MageObsidian/default@en_US', $this->output->fetch());
    }

    public function testNamesAFileSoTheGapCanBeChased(): void
    {
        $plugin = $this->plugin(missing: ['MageObsidian/default@en_US' => ['lib/vue.js']]);

        $plugin->afterDeploy($this->subject(), null, $this->options());

        $this->assertStringContainsString('lib/vue.js', $this->output->fetch());
    }

    // An incomplete bundle is worth reporting, but not worth undoing a deploy
    // that Magento itself considers finished.
    public function testLetsTheDeployFinishRegardless(): void
    {
        $plugin = $this->plugin(missing: ['MageObsidian/default@en_US' => ['lib/vue.js']]);

        $this->assertSame('deployed', $plugin->afterDeploy($this->subject(), 'deployed', $this->options()));
    }

    // Nothing was built, so there is nothing to verify and nothing to report.
    public function testSkipsWhenJavascriptWasExcluded(): void
    {
        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->expects($this->never())->method('findMissing');

        $options = $this->options();
        $options[DeployStaticOptions::NO_JAVASCRIPT] = true;

        $this->pluginWith($verifier)->afterDeploy($this->subject(), null, $options);
    }

    public function testSkipsWhenTheFrontendAreaIsNotBeingDeployed(): void
    {
        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->expects($this->never())->method('findMissing');

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
        $verifier->expects($this->once())->method('findMissing')->with($options)->willReturn([]);

        $this->pluginWith($verifier)->afterDeploy($this->subject(), null, $options);
    }

    /**
     * @param array<string, string[]> $missing
     */
    private function plugin(array $missing): VerifyViteContentPlugin
    {
        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->method('findMissing')->willReturn($missing);

        return $this->pluginWith($verifier);
    }

    private function pluginWith(ViteOutputVerifier $verifier): VerifyViteContentPlugin
    {
        return new VerifyViteContentPlugin($verifier, $this->output);
    }

    private function subject(): DeployStaticContent
    {
        return $this->createMock(DeployStaticContent::class);
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
