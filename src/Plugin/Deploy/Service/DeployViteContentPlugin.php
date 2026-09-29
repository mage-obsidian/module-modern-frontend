<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Service;

use Magento\Deploy\Console\DeployStaticOptions;
use Magento\Deploy\Service\DeployStaticContent;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use MageObsidian\ModernFrontend\Api\ConfigManagerInterface;
use MageObsidian\ModernFrontend\Model\Deploy\DeployTargets;
use MageObsidian\ModernFrontend\Model\Deploy\ViteBuildPreflight;
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
    public const string SKIP_BUILD_ENV_VAR = 'MAGE_OBSIDIAN_SKIP_VITE_BUILD';
    private const string OUTPUT_DIR = '/web/generated';

    public function __construct(
        private readonly ConfigManagerInterface $configManager,
        private readonly DeployTargets $deployTargets,
        private readonly ViteBuildRunner $runner,
        private readonly ViteBuildPreflight $preflight,
        private readonly DirectoryList $directoryList,
        private readonly OutputInterface $output
    ) {
    }

    /**
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

        $contractThemes = $this->configManager->generate()['themes'] ?? [];

        if (self::isBuildSkipped(getenv(self::SKIP_BUILD_ENV_VAR))) {
            $this->output->writeln(sprintf(
                '<comment>%s is set: the Vite build is skipped. The frontend contract was regenerated.</comment>',
                self::SKIP_BUILD_ENV_VAR
            ));
            return [$options];
        }

        $themes = array_values(array_filter(
            array_keys($contractThemes),
            fn (string $theme): bool => $this->deployTargets->includesTheme($theme, $options)
        ));
        if ($themes === []) {
            $this->output->writeln(
                '<comment>No MageObsidian theme is included in this deploy: nothing to build.</comment>'
            );
            return [$options];
        }

        $this->preflight->assertWritable(
            $this->directoryList->getRoot() . '/' . ViteBuildRunner::VITE_DIR,
            array_map(fn (string $theme): string => $contractThemes[$theme]['src'] . self::OUTPUT_DIR, $themes)
        );

        $this->output->writeln('<info>Starting Mage Obsidian Vite build generation...</info>');
        if (count($themes) === count($contractThemes)) {
            $this->runner->build();
        } else {
            foreach ($themes as $theme) {
                $this->runner->build($theme);
            }
        }
        $this->output->writeln('<info>Mage Obsidian Vite build generation finished.</info>');

        return [$options];
    }

    public static function isBuildSkipped(string|false $raw): bool
    {
        return $raw !== false && filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param string[] $areas
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
