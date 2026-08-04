<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Plugin\Deploy\Service;

use Magento\Deploy\Console\DeployStaticOptions;
use Magento\Deploy\Service\DeployStaticContent;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputVerifier;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Warns when the Vite bundle did not make it to `pub/static`.
 *
 * `setup:static-content:deploy` cannot report this on its own: a worker killed
 * mid-flight is marked completed, its exit status is logged as info and never
 * propagated, and the command exits 0 with packages half-published. The symptom
 * is a storefront that answers 200 while every one of its modules 404s, which is
 * why an HTTP health check does not catch it either.
 */
class VerifyViteContentPlugin
{
    /**
     * How many missing files to name before the message stops being useful.
     */
    private const SAMPLE_SIZE = 5;

    public function __construct(
        private readonly ViteOutputVerifier $verifier,
        private readonly OutputInterface $output
    ) {
    }

    /**
     * @param DeployStaticContent $subject
     * @param mixed $result
     * @param array $options
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterDeploy(DeployStaticContent $subject, $result, array $options)
    {
        if (
            ($options[DeployStaticOptions::NO_JAVASCRIPT] ?? false) === true
            || !$this->hasFrontendArea($options[DeployStaticOptions::AREA] ?? [])
            || $this->hasFrontendArea($options[DeployStaticOptions::EXCLUDE_AREA] ?? [])
        ) {
            return $result;
        }

        $missing = $this->verifier->findMissing($options);
        if ($missing !== []) {
            $this->warn(__(
                'Static content deployment did not publish the whole Vite build. %1. '
                . 'This usually means a deploy worker died: the command still exits 0, '
                . 'so re-run setup:static-content:deploy and check for killed processes.',
                $this->describe($missing)
            )->render());
        }

        return $result;
    }

    /**
     * Sent to stderr so the warning survives a deploy whose stdout is piped to
     * a log — the runs most likely to lose a worker are the unattended ones.
     */
    private function warn(string $message): void
    {
        $output = $this->output instanceof ConsoleOutputInterface
            ? $this->output->getErrorOutput()
            : $this->output;

        $output->writeln('<comment>' . $message . '</comment>');
    }

    /**
     * @param array<string, string[]> $missing
     */
    private function describe(array $missing): string
    {
        $described = [];
        foreach ($missing as $target => $files) {
            $sample = array_slice($files, 0, self::SAMPLE_SIZE);
            $described[] = sprintf(
                '%s is missing %d file(s) (%s%s)',
                $target,
                count($files),
                implode(', ', $sample),
                count($files) > count($sample) ? ', …' : ''
            );
        }

        return implode('; ', $described);
    }

    /**
     * @param string[] $areas
     */
    private function hasFrontendArea(array $areas): bool
    {
        return array_intersect($areas, DeployViteContentPlugin::AVAILABLE_AREAS) !== [];
    }
}
