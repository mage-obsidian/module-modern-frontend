<?php
declare(strict_types=1);
/**
 * This file is part of the Obsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Service\Cms;

use Magento\Framework\App\Area;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\ModernFrontend\Api\Data\ConfigInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The stylesheet for classes the build never saw.
 *
 * Derived, never appended to:
 *
 *     delta = classes(CMS content now) − classes(the build's baseline)
 *
 * Recomputing it whole is what makes the rest simple. There is no growing file
 * to prune, no write lock protecting accumulated state, and after a build the
 * difference is empty on its own — nobody has to remember to reset anything.
 *
 * One file per theme, because the same class compiles to different CSS against
 * different tokens. A failure anywhere leaves the previous file in place: a
 * merchant saving a page must never be told their content broke because a
 * stylesheet could not be built.
 */
class DeltaStylesheet
{
    public const string MEDIA_PATH = 'mage-obsidian/cms';
    public const string FILE = 'on-the-fly.css';
    private const string STATE_FILE = 'on-the-fly.json';

    private const string CACHE_KEY = 'mage_obsidian_cms_delta';
    private const string LOCK_NAME = 'mage_obsidian_cms_delta';
    private const int LOCK_TIMEOUT = 30;
    private const array EMPTY_STATE = ['classes' => 0, 'unresolved' => [], 'bytes' => 0, 'hash' => ''];

    public function __construct(
        private readonly ContentExporter $exporter,
        private readonly CmsBaseline $baseline,
        private readonly TailwindCli $tailwind,
        private readonly Filesystem $filesystem,
        private readonly Repository $assetRepository,
        private readonly DesignInterface $design,
        private readonly ThemeProviderInterface $themeProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly State $appState,
        private readonly CacheInterface $cache,
        private readonly LockManagerInterface $lockManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Rebuild the delta for every theme the storefront is configured to use.
     *
     * @return array{classes: int, unresolved: string[], bytes: int, themes: int, skipped: bool}
     */
    public function regenerate(): array
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, self::LOCK_TIMEOUT)) {
            return $this->state() + ['themes' => 0, 'skipped' => true];
        }

        try {
            // Read the content once: it is the same for every theme, and it is
            // the only part of this that touches the database.
            $candidates = $this->exporter->collectCandidates();
            $totals = ['classes' => 0, 'unresolved' => [], 'bytes' => 0, 'themes' => 0];

            foreach ($this->configuredThemes() as $theme) {
                $result = $this->buildFor($theme, $candidates);
                $totals['classes'] = max($totals['classes'], $result['classes']);
                $totals['bytes'] += $result['bytes'];
                $totals['unresolved'] = array_values(
                    array_unique(array_merge($totals['unresolved'], $result['unresolved']))
                );
                $totals['themes']++;
            }

            return $totals + ['skipped' => false];
        } catch (Throwable $e) {
            $this->logger->warning('MageObsidian: could not regenerate the CMS delta stylesheet: ' . $e->getMessage());

            return $this->state() + ['themes' => 0, 'skipped' => true];
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * What the last regeneration produced for a theme, without touching the
     * database. Defaults to the theme of the current request.
     *
     * @return array{classes: int, unresolved: string[], bytes: int, hash: string}
     */
    public function state(?string $themeKey = null): array
    {
        $themeKey ??= $this->currentThemeKey();

        $cached = $this->cache->load(self::CACHE_KEY . '_' . $themeKey);
        $decoded = $cached ? json_decode((string)$cached, true) : null;
        if (is_array($decoded)) {
            return $decoded + self::EMPTY_STATE;
        }

        // The cache is a memo, not the record. `cache:flush` is routine, and
        // losing this to one would drop the <link> from every page while the
        // stylesheet it points at is still on disk and still correct.
        $state = $this->readStateFile($themeKey);
        $this->cache->save((string)json_encode($state), self::CACHE_KEY . '_' . $themeKey);

        return $state;
    }

    public function hasDelta(): bool
    {
        return $this->state()['bytes'] > 0;
    }

    /**
     * Whether every configured theme was built with a class baseline.
     *
     * Asked here rather than of CmsBaseline directly because the baseline is a
     * theme file: resolving it needs a theme, and a command running outside a
     * storefront request has no design set.
     */
    public function hasBaseline(): bool
    {
        foreach ($this->configuredThemes() as $theme) {
            if (!$this->baseline->exists($theme)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Media-relative path of a theme's delta, e.g.
     * `mage-obsidian/cms/MageObsidian_default/on-the-fly.css`.
     */
    public function relativePath(?string $themeKey = null): string
    {
        return self::MEDIA_PATH . '/' . ($themeKey ?? $this->currentThemeKey()) . '/' . self::FILE;
    }

    /**
     * @param string[] $candidates
     *
     * @return array{classes: int, unresolved: string[], bytes: int, hash: string}
     */
    private function buildFor(ThemeInterface $theme, array $candidates): array
    {
        $delta = array_values(array_diff($candidates, $this->baseline->read($theme)));
        sort($delta);

        $css = $delta === [] ? '' : $this->tailwind->compile($delta, $this->themeSourceCss($theme));
        $themeKey = self::themeKey($theme);

        $result = [
            'classes' => count($delta),
            'unresolved' => self::unresolved($delta, $css),
            'bytes' => strlen($css),
            'hash' => $css === '' ? '' : hash('xxh128', $css),
        ];

        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $media->writeFile($this->relativePath($themeKey), $css);
        $media->writeFile($this->statePath($themeKey), (string)json_encode($result));

        // Untagged on purpose. This entry describes a file we just wrote; letting
        // a CMS cache flush clear it would leave the storefront believing there
        // is no delta while the stylesheet on disk says otherwise.
        $this->cache->save((string)json_encode($result), self::CACHE_KEY . '_' . $themeKey);

        return $result;
    }

    /**
     * @return array{classes: int, unresolved: string[], bytes: int, hash: string}
     */
    private function readStateFile(string $themeKey): array
    {
        try {
            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $path = $this->statePath($themeKey);
            if (!$media->isExist($path)) {
                return self::EMPTY_STATE;
            }
            $decoded = json_decode((string)$media->readFile($path), true);

            return is_array($decoded) ? $decoded + self::EMPTY_STATE : self::EMPTY_STATE;
        } catch (Throwable $e) {
            $this->logger->warning('MageObsidian: could not read the CMS delta state: ' . $e->getMessage());

            return self::EMPTY_STATE;
        }
    }

    private function statePath(string $themeKey): string
    {
        return self::MEDIA_PATH . '/' . $themeKey . '/' . self::STATE_FILE;
    }

    /**
     * Every distinct theme the frontend is configured with, across stores. A
     * class compiles differently against different tokens, so a multi-theme
     * store needs one file per theme, not one for whichever theme happened to
     * be current when the save ran.
     *
     * @return ThemeInterface[]
     */
    private function configuredThemes(): array
    {
        $themes = [];
        foreach ($this->storeManager->getStores() as $store) {
            $themeId = $this->scopeConfig->getValue(
                DesignInterface::XML_PATH_THEME_ID,
                ScopeInterface::SCOPE_STORE,
                $store->getId()
            );
            if (!$themeId) {
                continue;
            }
            $theme = $this->themeProvider->getThemeById((int)$themeId);
            if ($theme->getId() && !isset($themes[$theme->getCode()])) {
                $themes[$theme->getCode()] = $theme;
            }
        }

        return array_values($themes);
    }

    /**
     * Absolute path to a theme's `theme.source.css`, resolved through the theme
     * fallback so a child theme without its own file inherits the parent's
     * tokens — the same file the build compiled against.
     */
    private function themeSourceCss(ThemeInterface $theme): string
    {
        // Emulated rather than assumed: this runs from a cron job, from an
        // observer on an admin save and from bin/magento, none of which are in
        // the frontend area, and asset resolution throws without one.
        return $this->appState->emulateAreaCode(
            Area::AREA_FRONTEND,
            fn (): string => $this->assetRepository
                ->createAsset(
                    'css/' . ConfigInterface::THEME_CSS_SOURCE_FILE,
                    ['area' => Area::AREA_FRONTEND, 'themeModel' => $theme]
                )
                ->getSourceFile()
        );
    }

    /**
     * The theme of the current request. Falls back to the default store's
     * configured theme, because observers and cron run with no design set.
     */
    private function currentThemeKey(): string
    {
        // Outside a storefront request there is no design, and depending on how
        // the process started that is either an empty code or no theme at all.
        $current = $this->design->getDesignTheme();
        if ($current !== null && (string)$current->getCode() !== '') {
            return self::themeKey($current);
        }

        $themes = $this->configuredThemes();

        return $themes === [] ? 'default' : self::themeKey($themes[0]);
    }

    private static function themeKey(ThemeInterface $theme): string
    {
        return str_replace('/', '_', (string)$theme->getCode());
    }

    /**
     * Classes the delta asked for that Tailwind did not generate a rule for.
     *
     * Tailwind drops what it does not recognise without a word, so a typo or a
     * class from some other framework would just never show up. Naming them is
     * the difference between "your class is not a Tailwind utility" and silence.
     *
     * @param string[] $delta
     *
     * @return string[]
     */
    private static function unresolved(array $delta, string $css): array
    {
        if ($delta === []) {
            return [];
        }

        return array_values(array_filter(
            $delta,
            static fn (string $class): bool => !str_contains($css, '.' . self::escapeSelector($class))
        ));
    }

    /**
     * The CSS-escaped form of a class name, matching how Tailwind writes the
     * selector: `md:grid-cols-3` is emitted as `.md\:grid-cols-3`.
     */
    private static function escapeSelector(string $class): string
    {
        return preg_replace('~([^a-zA-Z0-9_-])~', '\\\\$1', $class) ?? $class;
    }
}
