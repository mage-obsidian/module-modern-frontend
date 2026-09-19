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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputPublisher;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputTarget;
use MageObsidian\ModernFrontend\Model\Deploy\ViteOutputVerifier;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Puts the Vite bundle in `pub/static` when the deploy did not, and says so.
 *
 * `setup:static-content:deploy` cannot report this on its own: a worker killed
 * mid-flight is marked completed, its exit status is logged as info and never
 * propagated, and the command exits 0 with packages half-published. The symptom
 * is a storefront that answers 200 while every one of its modules 404s, which is
 * why an HTTP health check does not catch it either.
 *
 * The other half is quieter and does not 404 at all: Magento's publisher skips a
 * destination that already exists, so a rebuilt bundle under a stable filename —
 * the theme stylesheet, every enhancer, every island — keeps serving the copy
 * published the first time. Repairing it here rather than only reporting it is
 * the difference between a deploy that is correct and one that needs somebody to
 * know to empty `pub/static` first.
 */
class VerifyViteContentPlugin
{
    /**
     * How many missing files to name before the message stops being useful.
     */
    private const SAMPLE_SIZE = 5;

    public const string STRICT_ENV_VAR = 'MAGE_OBSIDIAN_STRICT_DEPLOY';

    public function __construct(
        private readonly ViteOutputVerifier $verifier,
        private readonly ViteOutputPublisher $publisher,
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

        $unbuilt = $this->verifier->findUnbuilt($options);
        if ($unbuilt !== []) {
            $this->report(__(
                'Mage Obsidian found no Vite build for %1. Its storefront will load without its assets. '
                . 'Run the Vite build before setup:static-content:deploy.',
                implode(', ', $unbuilt)
            )->render());
        }

        $outdated = $this->verifier->findOutdated($options);
        if ($outdated === []) {
            return $result;
        }

        $republished = 0;
        $failures = [];
        foreach ($outdated as $target) {
            $failed = $this->publisher->publish($target);
            $republished += count($target->files) - count($failed);
            if ($failed !== []) {
                $failures[] = new ViteOutputTarget(
                    $target->theme,
                    $target->locale,
                    $target->sourceDirectory,
                    $target->targetDirectory,
                    $failed
                );
            }
        }

        if ($republished > 0) {
            $this->output->writeln(__(
                '<info>Mage Obsidian published %1 Vite file(s) the static content deploy left out of date.</info>',
                $republished
            )->render());
        }

        if ($failures !== []) {
            $this->report(__(
                'Static content deployment did not publish the whole Vite build, and the missing files '
                . 'could not be copied either. %1. This usually means a deploy worker died: the command '
                . 'still exits 0, so re-run setup:static-content:deploy and check for killed processes.',
                $this->describe($failures)
            )->render());
        }

        return $result;
    }

    private function report(string $message): void
    {
        if (getenv(self::STRICT_ENV_VAR) === '1') {
            throw new LocalizedException(new Phrase('%1', [$message]));
        }
        $this->warn($message);
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
     * @param ViteOutputTarget[] $failures
     */
    private function describe(array $failures): string
    {
        $described = [];
        foreach ($failures as $target) {
            $sample = array_slice($target->files, 0, self::SAMPLE_SIZE);
            $described[] = sprintf(
                '%s is missing %d file(s) (%s%s)',
                $target->label(),
                count($target->files),
                implode(', ', $sample),
                count($target->files) > count($sample) ? ', …' : ''
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
