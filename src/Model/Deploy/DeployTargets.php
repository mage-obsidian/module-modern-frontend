<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\Deploy;

use Magento\Deploy\Console\DeployStaticOptions;
use Magento\Deploy\Package\LocaleResolver;
use Magento\Deploy\Package\Package;
use Magento\Deploy\Package\PackageFactory;
use Magento\Framework\App\Area;
use Throwable;

/**
 * Works out which themes and locales a `setup:static-content:deploy` run
 * actually covered, so its output can be checked against the right paths.
 *
 * The deploy options cannot be read literally. `--language` defaults to the
 * sentinel `all`, which Magento expands to the locales its store views use
 * (`Magento\Deploy\Package\PackagePool::ensureRequiredLocales()`); taking it at
 * face value would look for a `pub/static/<area>/<theme>/all/` that is never
 * written. `--theme` and the `--exclude-*` counterparts carry the same kind of
 * sentinel, and their precedence rules are Magento's, not the obvious ones.
 */
class DeployTargets
{
    private const INCLUDE_ALL = 'all';

    private const EXCLUDE_NONE = 'none';

    /**
     * @var string[]|null
     */
    private ?array $frontendLocales = null;

    public function __construct(
        private readonly LocaleResolver $localeResolver,
        private readonly PackageFactory $packageFactory
    ) {
    }

    /**
     * Locales the run published to, with the `all` sentinel already expanded.
     *
     * @param array<string, mixed> $options
     * @return string[]
     */
    public function locales(array $options): array
    {
        $include = $this->option($options, DeployStaticOptions::LANGUAGE);
        $exclude = $this->option($options, DeployStaticOptions::EXCLUDE_LANGUAGE);

        $candidates = $this->includesEverything($include) ? $this->frontendLocales() : $include;

        return array_values(array_filter(
            $candidates,
            fn (string $locale): bool => $this->isIncluded($locale, $include, $exclude)
        ));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function includesTheme(string $theme, array $options): bool
    {
        return $this->isIncluded(
            self::normalize($theme),
            array_map([self::class, 'normalize'], $this->option($options, DeployStaticOptions::THEME)),
            array_map([self::class, 'normalize'], $this->option($options, DeployStaticOptions::EXCLUDE_THEME))
        );
    }

    /**
     * Asking the deploy's own resolver keeps this from drifting: it already
     * knows to fall back to the distro locale when no database is reachable,
     * which is how static content gets deployed in a build pipeline.
     *
     * @return string[]
     */
    private function frontendLocales(): array
    {
        if ($this->frontendLocales !== null) {
            return $this->frontendLocales;
        }

        try {
            $package = $this->packageFactory->create([
                'area' => Area::AREA_FRONTEND,
                'theme' => Package::BASE_THEME,
                'locale' => Package::BASE_LOCALE,
            ]);

            $this->frontendLocales = $this->localeResolver->getUsedPackageLocales($package);
        } catch (Throwable) {
            // Verification must never be the thing that breaks a deploy, so an
            // unresolvable locale list means "nothing to check", not a failure.
            $this->frontendLocales = [];
        }

        return $this->frontendLocales;
    }

    /**
     * Mirrors `PackagePool::isIncluded()`, including its quirk that any
     * exclusion list makes the inclusion list irrelevant. Deviating would flag
     * packages the deploy never intended to write.
     *
     * @param string[] $include
     * @param string[] $exclude
     */
    private function isIncluded(string $entity, array $include, array $exclude): bool
    {
        if (!$this->excludesNothing($exclude)) {
            return !in_array($entity, $exclude, true);
        }

        if (!$this->includesEverything($include)) {
            return in_array($entity, $include, true);
        }

        return true;
    }

    /**
     * @param string[] $include
     */
    private function includesEverything(array $include): bool
    {
        return $include === [] || $include[0] === self::INCLUDE_ALL;
    }

    /**
     * @param string[] $exclude
     */
    private function excludesNothing(array $exclude): bool
    {
        return $exclude === [] || $exclude[0] === self::EXCLUDE_NONE;
    }

    /**
     * @param array<string, mixed> $options
     * @return string[]
     */
    private function option(array $options, string $name): array
    {
        $value = $options[$name] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    private static function normalize(string $theme): string
    {
        return str_replace('_', '/', $theme);
    }
}
