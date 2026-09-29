<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Service;

use Magento\Deploy\Console\DeployStaticOptions;
use Magento\Deploy\Service\DeployStaticContent;
use Magento\Framework\Exception\LocalizedException;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Model\Deploy\ViteBuildRunner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drives the Vite build before Magento materializes static content.
 *
 * The Vite bundle is locale-agnostic, so it is built once per modern theme and
 * written to the theme source (`web/generated`). Magento's native deploy
 * pipeline then publishes that output to `pub/static/<area>/<theme>/<locale>/`
 * for every locale on its own — this plugin only produces the bundle, it does
 * not copy anything to pub/static.
 */
class DeployViteContentPlugin
{
    public const AVAILABLE_AREAS = ['frontend', 'all'];

    public function __construct(
        private readonly ConfigManagerInterface $configManager,
        private readonly ViteBuildRunner $runner,
        private readonly OutputInterface $output
    ) {
    }

    /**
     * @param DeployStaticContent $subject
     * @param array $options
     * @return array
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDeploy(DeployStaticContent $subject, array $options): array
    {
        if (
            $options[DeployStaticOptions::NO_JAVASCRIPT] === true ||
            !$this->hasFrontendArea($options[DeployStaticOptions::AREA]) ||
            $this->hasFrontendArea($options[DeployStaticOptions::EXCLUDE_AREA])
        ) {
            return [$options];
        }

        $themes = $options[DeployStaticOptions::THEME] ?? [];
        $this->output->writeln('<info>Starting Mage Obsidian Vite build generation...</info>');

        if (in_array('all', $themes, true)) {
            $this->runner->build();
        } else {
            foreach ($themes as $theme) {
                if (!$this->configManager->isThemeEnabled($theme)) {
                    continue;
                }
                $this->runner->build($theme);
            }
        }

        $this->output->writeln('<info>Mage Obsidian Vite build generation finished.</info>');
        return [$options];
    }

    /**
     * @param string[] $areas
     * @return bool
     */
    private function hasFrontendArea(array $areas): bool
    {
        foreach ($areas as $area) {
            if (in_array($area, self::AVAILABLE_AREAS, true)) {
                return true;
            }
        }
        return false;
    }
}
