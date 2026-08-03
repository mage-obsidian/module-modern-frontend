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
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputVerifier;
use MageObsidian\ModernFrontend\Plugin\Deploy\Service\VerifyViteContentPlugin;
use PHPUnit\Framework\TestCase;

class VerifyViteContentPluginTest extends TestCase
{
    public function testLetsACompleteDeployThrough(): void
    {
        $plugin = $this->plugin(missing: []);

        $this->assertNull($plugin->afterDeploy($this->subject(), null, $this->options()));
    }

    /**
     * The whole point: a deploy whose workers died exits 0, so the only way to
     * stop a storefront being served without its JavaScript is to check the
     * result and refuse to call it a success.
     */
    public function testFailsTheDeployWhenTheBundleIsMissing(): void
    {
        $plugin = $this->plugin(missing: [
            'MageObsidian/default@en_US' => ['lib/vue.js', 'MageObsidian_Storefront/js/nav.js'],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/MageObsidian\/default@en_US/');

        $plugin->afterDeploy($this->subject(), null, $this->options());
    }

    public function testNamesAFileSoTheFailureCanBeChased(): void
    {
        $plugin = $this->plugin(missing: ['MageObsidian/default@en_US' => ['lib/vue.js']]);

        try {
            $plugin->afterDeploy($this->subject(), null, $this->options());
            $this->fail('Expected the deploy to be rejected.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('lib/vue.js', $e->getMessage());
        }
    }

    // Nothing was built, so there is nothing to verify and no reason to fail.
    public function testSkipsWhenJavascriptWasExcluded(): void
    {
        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->expects($this->never())->method('findMissing');

        $options = $this->options();
        $options[DeployStaticOptions::NO_JAVASCRIPT] = true;

        (new VerifyViteContentPlugin($verifier))->afterDeploy($this->subject(), null, $options);
    }

    public function testSkipsWhenTheFrontendAreaIsNotBeingDeployed(): void
    {
        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->expects($this->never())->method('findMissing');

        $options = $this->options();
        $options[DeployStaticOptions::AREA] = ['adminhtml'];

        (new VerifyViteContentPlugin($verifier))->afterDeploy($this->subject(), null, $options);
    }

    public function testChecksTheLocalesThatWereDeployed(): void
    {
        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->expects($this->once())
            ->method('findMissing')
            ->with(['en_US', 'es_ES'])
            ->willReturn([]);

        $options = $this->options();
        $options[DeployStaticOptions::LANGUAGE] = ['en_US', 'es_ES'];

        (new VerifyViteContentPlugin($verifier))->afterDeploy($this->subject(), null, $options);
    }

    /**
     * @param array<string, string[]> $missing
     */
    private function plugin(array $missing): VerifyViteContentPlugin
    {
        $verifier = $this->createMock(ViteOutputVerifier::class);
        $verifier->method('findMissing')->willReturn($missing);

        return new VerifyViteContentPlugin($verifier);
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
            DeployStaticOptions::LANGUAGE => ['en_US'],
        ];
    }
}
