<?php
declare(strict_types=1);

namespace MageObsidian\ModernFrontend\Test\Unit\Service\Contract;

use MageObsidian\ModernFrontend\Service\Contract\ContractDiff;
use PHPUnit\Framework\TestCase;

class ContractDiffTest extends TestCase
{
    public function testSectionDetectsAddedRemovedAndChanged(): void
    {
        $current = [
            'Vendor_Kept' => ['src' => '/a'],
            'Vendor_Removed' => ['src' => '/b'],
            'Vendor_Changed' => ['src' => '/c'],
        ];
        $expected = [
            'Vendor_Kept' => ['src' => '/a'],
            'Vendor_Changed' => ['src' => '/c-moved'],
            'Vendor_Added' => ['src' => '/d'],
        ];

        $this->assertSame(
            ['added' => ['Vendor_Added'], 'removed' => ['Vendor_Removed'], 'changed' => ['Vendor_Changed']],
            ContractDiff::section($current, $expected)
        );
    }

    public function testSectionIsCleanWhenIdentical(): void
    {
        $entries = ['Vendor_A' => ['src' => '/a'], 'Vendor_B' => ['src' => '/b']];

        $this->assertSame(
            ['added' => [], 'removed' => [], 'changed' => []],
            ContractDiff::section($entries, $entries)
        );
    }

    public function testIsEmptyTrueOnlyWhenEverySectionClean(): void
    {
        $clean = ['added' => [], 'removed' => [], 'changed' => []];
        $dirty = ['added' => ['x'], 'removed' => [], 'changed' => []];

        $this->assertTrue(ContractDiff::isEmpty(['modules' => $clean, 'themes' => $clean]));
        $this->assertFalse(ContractDiff::isEmpty(['modules' => $clean, 'themes' => $dirty]));
    }

    public function testSummarizeCountsPerSection(): void
    {
        $drift = [
            'modules' => ['added' => ['a'], 'removed' => [], 'changed' => ['b']],
            'themes' => ['added' => [], 'removed' => ['t'], 'changed' => []],
        ];

        $this->assertSame('modules +1 -0 ~1; themes +0 -1 ~0', ContractDiff::summarize($drift));
    }

    /**
     * The flag-flip scenario: a module enabled on disk whose compatibility was
     * turned off (so it is gone from the recomputed set) shows up as removed.
     */
    public function testCompatibilityFlagFlipSurfacesAsRemoved(): void
    {
        $onDisk = ['Vendor_WasCompatible' => ['src' => '/x']];
        $recomputed = [];

        $section = ContractDiff::section($onDisk, $recomputed);

        $this->assertSame(['Vendor_WasCompatible'], $section['removed']);
        $this->assertFalse(ContractDiff::isEmpty(['modules' => $section]));
    }
}
