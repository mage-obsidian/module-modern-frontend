<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service;

use MageObsidian\ModernFrontend\Service\IslandManifest;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the pure transitive-closure logic of {@see IslandManifest::collectPreloadFiles()}.
 * The static method touches no Magento types, so it runs on the standalone
 * bootstrap (loading the class never loads its constructor's type-hints).
 */
class IslandManifestTest extends TestCase
{
    /**
     * A manifest shaped like the real island chain: a component entry whose
     * static imports fan out to customer-data → section-store → store → pinia,
     * with vue reachable through two paths (so dedup is observable).
     *
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        return [
            'WishlistCount.vue' => [
                'file' => 'MageObsidian_Storefront/components/wishlist/WishlistCount.js',
                'isEntry' => true,
                'imports' => ['_customer-data.js', '_vue.js'],
            ],
            'CartCount.vue' => [
                'file' => 'MageObsidian_Storefront/components/cart/CartCount.js',
                'isEntry' => true,
                'imports' => ['_customer-data.js'],
            ],
            'Toast.vue' => [
                'file' => 'MageObsidian_Storefront/components/feedback/Toast.js',
                'isEntry' => true,
                'imports' => ['_vue.js'],
            ],
            '_customer-data.js' => [
                'file' => 'MageObsidian_ModernFrontend/js/customer-data.js',
                'imports' => ['_section-store.js'],
            ],
            '_section-store.js' => [
                'file' => 'assets/section-store-D-Nj1XEV.js',
                'imports' => ['_store.js', '_pinia.js', '_vue.js'],
            ],
            '_store.js' => [
                'file' => 'MageObsidian_ModernFrontend/js/store.js',
                'imports' => ['_pinia.js'],
            ],
            '_pinia.js' => [
                'file' => 'lib/pinia.js',
                'imports' => [],
            ],
            '_vue.js' => [
                'file' => 'assets/vue.esm-browser.prod-C2mwSwPj.js',
                'imports' => [],
            ],
        ];
    }

    public function testCollectsTheWholeTransitiveChainDeduplicated(): void
    {
        $files = IslandManifest::collectPreloadFiles(
            $this->manifest(),
            ['MageObsidian_Storefront/components/wishlist/WishlistCount.js']
        );

        sort($files);
        $this->assertSame(
            [
                'MageObsidian_ModernFrontend/js/customer-data.js',
                'MageObsidian_ModernFrontend/js/store.js',
                'MageObsidian_Storefront/components/wishlist/WishlistCount.js',
                'assets/section-store-D-Nj1XEV.js',
                'assets/vue.esm-browser.prod-C2mwSwPj.js',
                'lib/pinia.js',
            ],
            $files
        );
    }

    public function testDeduplicatesSharedDepsAcrossMultipleStartChunks(): void
    {
        $files = IslandManifest::collectPreloadFiles(
            $this->manifest(),
            [
                'MageObsidian_Storefront/components/wishlist/WishlistCount.js',
                'MageObsidian_Storefront/components/cart/CartCount.js',
                'MageObsidian_Storefront/components/feedback/Toast.js',
            ]
        );

        // pinia/vue/section-store/customer-data appear once despite being reached
        // through several components.
        $this->assertSame(count($files), count(array_unique($files)));
        $this->assertContains('lib/pinia.js', $files);
        $this->assertContains('assets/vue.esm-browser.prod-C2mwSwPj.js', $files);
        $this->assertContains('MageObsidian_Storefront/components/feedback/Toast.js', $files);
    }

    public function testNormalizesMissingJsExtensionOnStartChunk(): void
    {
        $files = IslandManifest::collectPreloadFiles(
            $this->manifest(),
            ['MageObsidian_Storefront/components/feedback/Toast']
        );

        sort($files);
        $this->assertSame(
            [
                'MageObsidian_Storefront/components/feedback/Toast.js',
                'assets/vue.esm-browser.prod-C2mwSwPj.js',
            ],
            $files
        );
    }

    public function testUnknownStartChunkYieldsEmptySet(): void
    {
        $this->assertSame(
            [],
            IslandManifest::collectPreloadFiles($this->manifest(), ['Vendor/components/Nope.js'])
        );
    }

    public function testEmptyInputsYieldEmptySet(): void
    {
        $this->assertSame([], IslandManifest::collectPreloadFiles([], ['whatever.js']));
        $this->assertSame([], IslandManifest::collectPreloadFiles($this->manifest(), []));
    }
}
