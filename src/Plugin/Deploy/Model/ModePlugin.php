<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Model;

use Magento\Deploy\Model\Mode;
use MageObsidian\ModernFrontend\Service\Dev\ModeAdvisor;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Surfaces actionable MageObsidian guidance right after `deploy:mode:set`
 * switches the application mode, so the developer knows what the modern frontend
 * expects (dev server in developer, built assets in default/production).
 *
 * Advisory only — the hard rules live elsewhere (HMR off in production via
 * ConfigProvider; the production static deploy builds the Vite bundle via
 * DeployViteContentPlugin). This plugin never changes behavior, only prints.
 */
class ModePlugin
{
    public function __construct(
        private readonly OutputInterface $output
    ) {
    }

    public function afterEnableDeveloperMode(Mode $subject, mixed $result): mixed
    {
        $this->advise(ModeAdvisor::MODE_DEVELOPER);
        return $result;
    }

    public function afterEnableDefaultMode(Mode $subject, mixed $result): mixed
    {
        $this->advise(ModeAdvisor::MODE_DEFAULT);
        return $result;
    }

    public function afterEnableProductionMode(Mode $subject, mixed $result): mixed
    {
        $this->advise(ModeAdvisor::MODE_PRODUCTION);
        return $result;
    }

    private function advise(string $mode): void
    {
        $messages = ModeAdvisor::messagesForMode($mode);
        if ($messages === []) {
            return;
        }
        $this->output->writeln('');
        foreach ($messages as $message) {
            $this->output->writeln('<info>' . $message . '</info>');
        }
    }
}
