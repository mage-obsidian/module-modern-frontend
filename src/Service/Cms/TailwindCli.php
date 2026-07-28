<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Cms;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Compiles a set of class names into CSS with Tailwind's standalone binary.
 *
 * A merchant writing a class after the theme was built needs it generated where
 * the store runs, and the standalone build is the only way to do that without a
 * JS toolchain in production. It is not shipped — the doctor reports whether it
 * is there, and the docs carry the one-line install.
 *
 * Every failure is a logged warning and an empty string. Saving a page must not
 * depend on a binary being present.
 */
class TailwindCli
{
    public const string CONFIG_PATH = 'mage_obsidian/cms/tailwind_bin';
    private const string DEFAULT_BIN = 'bin/tailwindcss';
    private const string WORK_DIR = 'mage-obsidian/cms-jit';
    private const float TIMEOUT = 60.0;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly DirectoryList $directoryList,
        private readonly File $fileDriver,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getBinaryPath(): string
    {
        $configured = trim((string)$this->scopeConfig->getValue(self::CONFIG_PATH));
        if ($configured === '') {
            return $this->directoryList->getRoot() . '/' . self::DEFAULT_BIN;
        }

        return str_starts_with($configured, '/')
            ? $configured
            : $this->directoryList->getRoot() . '/' . ltrim($configured, '/');
    }

    public function isAvailable(): bool
    {
        $bin = $this->getBinaryPath();

        return $this->fileDriver->isExists($bin) && is_executable($bin);
    }

    /**
     * Version string the binary reports, or an empty string when it cannot run.
     */
    public function getVersion(): string
    {
        if (!$this->isAvailable()) {
            return '';
        }

        $process = new Process([$this->getBinaryPath(), '--help']);
        $process->setTimeout(self::TIMEOUT);
        $process->run();
        preg_match('~tailwindcss v([\d.]+)~', $process->getOutput() . $process->getErrorOutput(), $matches);

        return $matches[1] ?? '';
    }

    /**
     * @param string[] $classes Class names to generate rules for.
     * @param string $themeSourceCss Absolute path to the theme's `theme.source.css`.
     *
     * @return string The generated CSS, or an empty string if nothing could be produced.
     */
    public function compile(array $classes, string $themeSourceCss): string
    {
        if ($classes === [] || !$this->isAvailable()) {
            return '';
        }

        $work = $this->directoryList->getPath(DirectoryList::VAR_DIR)
            . '/' . str_replace('/', DIRECTORY_SEPARATOR, self::WORK_DIR);
        $content = $work . '/delta.html';
        $input = $work . '/input.css';
        $output = $work . '/out.css';

        try {
            if (!$this->fileDriver->isDirectory($work)) {
                $this->fileDriver->createDirectory($work);
            }
            $this->fileDriver->filePutContents($content, self::asMarkup($classes));
            $this->fileDriver->filePutContents($input, self::inputCss($themeSourceCss, $content));

            $process = new Process([$this->getBinaryPath(), '-i', $input, '-o', $output]);
            $process->setTimeout(self::TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->warning(
                    'MageObsidian: the Tailwind CLI failed to compile the CMS delta: '
                    . trim($process->getErrorOutput() ?: $process->getOutput())
                );

                return '';
            }

            return (string)$this->fileDriver->fileGetContents($output);
        } catch (Throwable $e) {
            $this->logger->warning('MageObsidian: could not compile the CMS delta: ' . $e->getMessage());

            return '';
        } finally {
            foreach ([$content, $input, $output] as $tmp) {
                if ($this->fileDriver->isExists($tmp)) {
                    $this->fileDriver->deleteFile($tmp);
                }
            }
        }
    }

    /**
     * `layer(utilities)` puts the result in the same cascade layer the built
     * stylesheet declares, so a class generated here behaves exactly like one
     * that came out of the build. `@reference` pulls in the theme's tokens
     * without emitting any of its rules, and `source(none)` stops Tailwind from
     * scanning anything but the file we hand it.
     */
    private static function inputCss(string $themeSourceCss, string $contentFile): string
    {
        return <<<CSS
        @reference "{$themeSourceCss}";
        @import "tailwindcss/utilities" layer(utilities) source(none);
        @source "{$contentFile}";
        CSS;
    }

    /**
     * Written raw, not escaped: the scanner matches the literal text, and a
     * variant like `[&>*]:p-4` would stop matching as `[&gt;*]:p-4`. A class can
     * never carry a double quote — it was read out of a `class="…"` attribute —
     * so nothing can break out of the one below.
     *
     * @param string[] $classes
     */
    private static function asMarkup(array $classes): string
    {
        $safe = array_filter($classes, static fn (string $class): bool => !str_contains($class, '"'));

        return '<div class="' . implode(' ', $safe) . '"></div>';
    }
}
